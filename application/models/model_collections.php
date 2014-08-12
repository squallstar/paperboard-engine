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
    return array('_id' => false, 'sources' => false, 'feeds' => false);
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
      'owned_collection' => true,
      'publicly_visible' => true,
      'partner_identifier' => null,
      'type' => 'standard',
      'total_links_count' => 0,
      'average_rating' => 0.0,
      'total_ratings' => 0,
      'changed_since_last_updated' => false,
      'last_updated_at' => time(),
      'filters_updated_at' => time(),
      'settings' => array(
        'color' => 'green',
        'links' => array(
          'displayStyle' => 'headline+image'
        )
      ),
      'filters' => array(),
      'user' => array(
        'id' => $this->users->get('_id'),
        'full_name' => $this->users->get('full_name'),
        'image_url' => $this->users->get('avatar')['small']
      ),
      'sources' => array(),
      'feeds' => array()
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
    $q = $this->_where_id($collection_id);
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
    return collection('collections')->findOne($q, $fields);
  }

  public function find_mine($include_feeds = false)
  {
    $fields = $this->collections->_default_excluded_fields();
    if ($include_feeds && isset($fields['feeds'])) unset($fields['feeds']);

    return iterator_to_array(
      collection('collections')->find(
        array(
          'user.id' => $this->users->get('_id')
        ),
        $fields
      )->sort(['position' => 1]),
      false
    );
  }

  private function _where_id($collection_id)
  {
    if (strpos($collection_id, 'p') === 0)
    {
      return array('private_id' => $collection_id);
    } else {
      $this->users->load_user();
      return array(
        'id' => intval($collection_id),
        'user.id' => $this->users->get('_id')
      );
    }
  }

  public function links(&$collection, $limit = 30, $max_timestamp = null, $min_timestamp = null)
  {
    $limit = $limit ? intval($limit) : 30;

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
}