<?php
/**
 * user model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_collections extends CI_Model
{
  const MAX_LINKS_COUNT = 40000;

  public function __construct()
  {
    parent::__construct();
  }

  private function _default_excluded_fields()
  {
    return array('_id' => false, 'sources' => false, 'feeds' => false, 'followers' => false);
  }

  private function _filter_fields($data = array())
  {
    // These below are the fields that a user can update on a collection
    return array_intersect_key(
      $data,
      array(
        'name'             => true,
        'type'             => true,
        'position'         => true,
        'publicly_visible' => true,
        'settings'         => true,
        'filters'          => true,
        'sources'          => true,
        'category'         => true
      )
    );
  }

  private function _prepare($data = array())
  {
    $data = $this->_filter_fields($data);

    return array_replace_recursive(array(
      'id' => next_id('collection'),
      'private_id' => newid('p'),
      'name' => 'New collection',
      'description' => '',
      'position' => 0,
      'publicly_visible' => true,
      'partner_identifier' => null,
      'type' => 'standard',
      'total_links_count' => 0,
      'average_rating' => 0.0,
      'total_ratings' => 0,
      'last_updated_at' => time(),
      'settings' => array(
        'color' => 'green',
        'links' => array(
          'displayStyle' => 'headline+image'
        )
      ),
      'cover_asset' => [
        'fixed' => false
      ],
      'filters' => [],
      'followers' => [],
      'follower_count' => 0,
      'user' => array(
        'id' => $this->users->id(),
        'full_name' => $this->users->get('full_name'),
        'image_url' => $this->users->get('avatar')['small']
      ),
      'sources' => [],
      'feeds' => []
    ), $data);
  }

  public function create($data = array())
  {
    $data = $this->_prepare($data);

    $this->load->model('model_sources', 'sources');
    $data['feeds'] = $this->sources->tree($data['sources'], true);

    $data['total_links_count'] = $this->links_count($data);
    $data['total_source_count'] = count($data['feeds']);

    $res = collection('collections')->insert($data);

    if ($res)
    {
      collection('users')->update(
        ['_id' => $this->users->id()],
        ['$inc' => ['total_collections_count' => 1]]
      );

      unset($data['_id']);
      unset($data['feeds']);
      return $data;
    }

    return false;
  }

  public function update_position($collection_id, $position)
  {
    $collection = $this->find($collection_id, ['id' => true]);

    if ($collection)
    {
      $position = intval($position);

      if ($collection['owned_collection'])
      {
        return collection('collections')->update(
          ['id' => $collection['id']],
          ['$set' => ['position' => $position]]
        );
      }
      else if ($collection['is_followed'])
      {
        return collection('collections')->update(
          [
            'id' => $collection['id'],
            'followers.id' => $this->users->id()
          ],
          ['$set' =>
            ['followers.$.position' => $position]
          ]
        );
      }
    }

    return false;
  }

  public function update($collection_id, $data = array())
  {
    $collection = $this->find($collection_id, [
      'category' => false, 'settings' => false
    ]);

    if (!$collection || !$collection['owned_collection']) return false;

    if (isset($data['category']['id']) && $data['category']['id'] == false)
    {
      unset($data['category']);
    }

    $data = $this->_filter_fields($data);

    $needs_recount = false;

    if (isset($data['filters']))
    {
      $needs_recount = true;
    }
    else
    {
      $data['filters'] = $collection['filters'];
    }

    if (isset($data['sources']))
    {
      $needs_recount = true;
    }
    else if ($needs_recount)
    {
      $data['sources'] = $collection['sources'];
    }

    $data['last_updated_at'] = time();

    if ($needs_recount)
    {
      $this->load->model('model_sources', 'sources');
      $data['feeds'] = $this->sources->tree($data['sources'], true);

      $data['total_links_count'] = $this->links_count($data);
      $data['total_source_count'] = count($data['feeds']);
    }

    $res = collection('collections')->update(['id' => $collection['id']], ['$set' => $data]);

    unset($data['feeds']);
    if (isset($data['sources'])) unset($data['sources']);

    return $res ? $data : false;
  }

  public function delete($collection_id)
  {
    collection('users')->update(
      ['_id' => $this->users->id()],
      ['$inc' => ['total_collections_count' => -1]]
    );

    return collection('collections')->remove(
      array(
        'id' => $collection_id,
        'user.id' => $this->users->id()
      ),
      array(
        'justOne' => true
      )
    ) ? true : false;
  }

  public function find($collection_id, $fields = array())
  {
    $q = $this->_where_id($collection_id);

    $user_id = $this->users->id();

    foreach ($fields as $key => $val) {
      if ($val == true)
      {
        $fields['user.id'] = true;
        $fields['followers'] = [
          '$elemMatch' => ['id' => $user_id]
        ];
        break;
      }
    }

    $collection = collection('collections')->findOne($q, $fields);

    if ($collection)
    {
      $this->_set_ownerships($collection, $user_id);
    }

    return $collection;
  }

  private function _set_ownerships(&$collection, &$user_id)
  {
    $collection['is_followed'] = false;

    if ($collection['user']['id'] == $user_id)
    {
      $collection['owned_collection'] = true;
    }
    else
    {
      $collection['owned_collection'] = false;

      if (isset($collection['followers']) && count($collection['followers']))
      {
        foreach ($collection['followers'] as &$follower)
        {
          if ($follower['id'] == $user_id)
          {
            $collection['position'] = $follower['position'];
            $collection['is_followed'] = true;
            break;
          }
        }

        unset($follower);
      }
    }

    unset($user_id);
  }

  public function find_mine($include_feeds = false, $fields_to_include = array())
  {
    $fields = $this->collections->_default_excluded_fields();

    foreach ($fields_to_include as &$field)
    {
      if (isset($fields[$field])) unset($fields[$field]);
    }

    unset($field);

    // Needed to set the ownership
    unset($fields['followers']);

    if ($include_feeds && isset($fields['feeds'])) unset($fields['feeds']);

    $user_id = $this->users->id();

    $collections = iterator_to_array(
      collection('collections')->find(
        [
          '$or' => [
            ['user.id' => $user_id],
            ['followers.id' => $user_id]
          ]
        ],
        $fields
      )->sort(['position' => 1]),
      false
    );

    foreach ($collections as &$collection)
    {
      $this->_set_ownerships($collection, $user_id);
    }

    unset($collection);
    unset($user_id);

    return $collections;
  }

  private function _where_id($collection_id)
  {
    if (strpos($collection_id, 'p') === 0)
    {
      return ['private_id' => $collection_id];
    }

    return ['id' => intval($collection_id)];
  }

  public function links_count(&$collection)
  {
    return $this->_links($collection, 0, null, null, [], [], false)->limit(self::MAX_LINKS_COUNT)->count(true);
  }

  public function links_ordered(&$collection, $limit = 40, $max_timestamp = null, $min_timestamp = null, $fields = [], $conditions = [])
  {
    return $this->_links($collection, $limit, $max_timestamp, $min_timestamp, $fields, $conditions, true);
  }

  public function links_not_ordered(&$collection, $limit = 40, $max_timestamp = null, $min_timestamp = null, $fields = [], $conditions = [])
  {
    return $this->_links($collection, $limit, $max_timestamp, $min_timestamp, $fields, $conditions, false);
  }

  private function _links(&$collection, $limit, $max_timestamp, $min_timestamp, $fields, $conditions, $sort)
  {
    $limit = $limit ? intval($limit) : 40;

    if (isset($collection['feeds']) && is_array($collection['feeds']))
    {
      $conditions['source'] = array(
        '$in' => &$collection['feeds']
      );
    }

    if ($max_timestamp)
    {
      $conditions['published_at'] = array(
        '$lt' => intval($max_timestamp)
      );
    }

    if ($min_timestamp)
    {
      $conditions['published_at'] = array(
        '$gt' => intval($min_timestamp)
      );
    }

    if (isset($collection['filters']) && count($collection['filters']))
    {
      $text_filters = [];

      foreach ($collection['filters'] as $filter)
      {
        $negate = isset($filter['negate']) ? $filter['negate'] : false;

        if ($filter['context'] == 'keywords')
        {
          $text_filters[] = $negate == false ? $filter['filter_value'] : '-' . $filter['filter_value'];
        }
        else if ($filter['context'] == 'phrase')
        {
          if ($negate == false)
          {
            $text_filters[] = "\"" . $filter['filter_value'] . "\"";
          } else {
            $text_filters[] = '-\"' . $filter['filter_value'] . "\"";
          }
        }
      }

      $n_filters = count($text_filters);

      if ($n_filters)
      {
        if ($n_filters == 1 && strpos($text_filters[0], '-') === 0)
        {
          // Mongo can't search only negated documents
          array_unshift($text_filters, "");
        }

        $conditions['$text'] = array(
          '$search' => implode(' ', $text_filters)
        );
      }

      unset($n_filters);
    }

    if (isset($collection['article_ids']))
    {
      $conditions['id'] = ['$in' => $collection['article_ids']];
    }

    if (!count($fields))
    {
      $fields = array(
        '_id' => false,
        'source' => false,
        'fetched_at' => false,
        'images_processed' => false,
        'assets' => false,
        'has_image' => false
      );
    }

    $cursor = collection('articles')->find(
      $conditions,
      $fields
    );

    unset($conditions);
    unset($fields);

    if ($limit)
    {
      $cursor->limit($limit);
    }

    if ($sort)
    {
      $cursor->sort(['published_at' => -1]);
    }

    return $cursor;
  }

  public function find_by_category($slug, $limit = 30)
  {
    return collection('collections')->find(
      array(
        'publicly_visible' => true,
        'category.slug' => $slug
      ),
      array(
        '_id' => false,
        'feeds' => false,
        'sources' => false,
        'position' => false,
        'owned_collection' => false
      )
    )->limit($limit);
  }

  public function follow($collection_id)
  {
    return collection('collections')->update(
      $this->_where_id($collection_id),
      [
        '$addToSet' => [
          'followers' => [
            'id' => $this->users->id(),
            'followed_at' => time(),
            'position' => 0
          ]
        ],
        '$inc' => [
          'follower_count' => 1
        ]
      ]
    );
  }

  public function unfollow($collection_id)
  {
    return collection('collections')->update(
      $this->_where_id($collection_id),
      [
        '$pull' => [
          'followers' => array(
            'id' => $this->users->id()
          )
        ],
        '$inc' => [
          'follower_count' => -1
        ]
      ]
    );
  }
}
