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
    return array_intersect_key(
      $data,
      array(
        'name'             => true,
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
      'cover_asset' => [],
      'filters' => [],
      'followers' => [],
      'followers_count' => 0,
      'user' => array(
        'id' => $this->users->get('_id'),
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

    $data['total_links_count'] = $this->links($data, FALSE)->count();
    $data['total_source_count'] = count($data['feeds']);

    $res = collection('collections')->insert($data);

    if ($res)
    {
      collection('users')->update(
        ['_id' => $this->users->get('_id')],
        ['$inc' => ['total_collections_count' => 1]]
      );

      unset($data['_id']);
      unset($data['feeds']);
      return $data;
    }

    return false;
  }

  public function update_single_field($collection_id, $field, $value)
  {
    $q = $this->_where_id($collection_id);

    $data = [];
    $data[$field] = $value;

    return collection('collections')->update($q, array('$set' => $data));
  }

  public function update($collection_id, $data = array(), $return = false)
  {
    $q = $this->_where_id($collection_id, true, true);
    $data = $this->_filter_fields($data);

    if (isset($data['sources']))
    {
      $this->load->model('model_sources', 'sources');
      $data['feeds'] = $this->sources->tree($data['sources'], true);
    }

    $data['last_updated_at'] = time();

    $is_changing_feeds = isset($data['feeds']);

    if ($is_changing_feeds || isset($data['filters']))
    {
      if (!$is_changing_feeds)
      {
        $tmp = collection('collections')->findOne(
          $q,
          array('_id' => false, 'feeds' => true)
        );

        $data['feeds'] = $tmp['feeds'];
        unset($tmp);
      }

      $data['total_links_count'] = $this->links($data, FALSE)->count();
      $data['total_source_count'] = count($data['feeds']);

      if (!$is_changing_feeds) unset($data['feeds']);
    }

    if ($return)
    {
      //TODO: this doesn't return the owned_collection status
      return collection('collections')->findAndModify(
        $q,
        array('$set' => $data),
        $this->_default_excluded_fields(),
        array('new' => true)
      );
    }
    else
    {
      return collection('collections')->update($q, array('$set' => $data));
    }
  }

  public function delete($collection_id)
  {
    collection('users')->update(
      ['_id' => $this->users->get('_id')],
      ['$inc' => ['total_collections_count' => -1]]
    );

    return collection('collections')->remove(
      array(
        'id' => $collection_id
      ),
      array(
        'justOne' => true
      )
    ) ? true : false;
  }

  public function find($collection_id, $fields = array())
  {
    $q = $this->_where_id($collection_id);

    foreach ($fields as $key => $val) {
      if ($val == true)
      {
        $fields['user'] = true;
        break;
      }
    }

    $collection = collection('collections')->findOne($q, $fields);

    if ($collection)
    {
      $this->users->load_user();
      $collection['owned_collection'] = $collection['user']['id'] == $this->users->get('_id');
    }

    return $collection;
  }

  public function find_mine($include_feeds = false)
  {
    $fields = $this->collections->_default_excluded_fields();
    if ($include_feeds && isset($fields['feeds'])) unset($fields['feeds']);

    $user_id = $this->users->get('_id');

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
      $collection['owned_collection'] = $collection['user']['id'] == $user_id;
    }

    unset($collection);
    unset($user_id);

    return $collections;
  }

  private function _where_id($collection_id, $owned_or_followed = true, $owned = false)
  {
    if (strpos($collection_id, 'p') === 0)
    {
      return array('private_id' => $collection_id);
    }
    else
    {
      $this->users->load_user();
      $user_id = $this->users->get('_id');

      if ($owned)
      {
        return [
          'id' => intval($collection_id),
          'user.id' => $user_id
        ];
      }
      else
      {
        if ($owned_or_followed)
        {
          return [
            '$and' => [
              [
                'id' => intval($collection_id)
              ],
              [
                '$or' => [
                  ['user.id' => $user_id],
                  ['followers.id' => $user_id]
                ]
              ]
            ]
          ];
        }
        else
        {
          return ['id' => intval($collection_id)];
        }
      }
    }
  }

  public function links(&$collection, $limit = 40, $max_timestamp = null, $min_timestamp = null)
  {
    $limit = $limit ? intval($limit) : 40;

    $conditions = [];

    if (isset($collection['feeds']))
    {
      $conditions['source'] = array(
        '$in' => $collection['feeds']
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

      $c = count($text_filters);

      if ($c)
      {
        if ($c == 1 && strpos($text_filters[0], '-') === 0)
        {
          // Mongo can't search only negated documents
          array_unshift($text_filters, "");
        }

        $conditions['$text'] = array(
          '$search' => implode(' ', $text_filters)
        );
      }
    }

    if (isset($collection['article_ids']))
    {
      $conditions['id'] = ['$in' => $collection['article_ids']];
    }

    $cursor = collection('articles')->find(
      $conditions,
      array(
        '_id' => false,
        'source' => false,
        'fetched_at' => false,
        'images_processed' => false,
        'assets' => false,
        'type' => false,
        'lead_image_in_content' => false
      )
    );

    if ($limit !== FALSE)
    {
      $cursor->limit($limit)->sort(['published_at' => -1]);
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
      $this->_where_id($collection_id, false),
      [
        '$addToSet' => [
          'followers' => [
            'id' => $this->users->get('_id'),
            'followed_at' => time(),
            'position' => 0
          ]
        ],
        '$inc' => [
          'followers_count' => 1
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
            'id' => $this->users->get('_id')
          )
        ],
        '$inc' => [
          'followers_count' => -1
        ]
      ]
    );
  }
}
