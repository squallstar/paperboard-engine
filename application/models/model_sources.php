<?php
/**
 * feed sources model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_sources extends CI_Model
{
  public function __construct()
  {
    parent::__construct();
  }

  public function add_feed_category($name = 'New category')
  {
    $id = newid('c');

    $data = array(
      'id'          => $id,
      'text'        => strip_tags($name),
      'type'        => 'feed_category',
      'source_uri'  => 'category:' . $id,
      'user_id'     => $this->users->get('_id'),
      'can_be_renamed' => true,
      'can_be_deleted' => true,
      'can_be_hidden' => false,
      'can_be_feed_parent' => true,
      'child_count' => 0,
      'broken' => false
    );

    $res = collection('user_categories')->save($data);

    unset($data['_id']);
    unset($data['user_id']);

    return $res ? $data : false;
  }

  public function add_twitter_category($id, $screen_name, $full_name = '')
  {
    $data = array(
      'id'          => $id,
      'text'        => $screen_name,
      'sub_text'    => $full_name,
      'type'        => 'twitter_account',
      'source_uri'  => 'twitter_account:' . $id,
      'user_id'     => $this->users->get('_id'),
      'can_be_renamed' => false,
      'can_be_deleted' => true,
      'can_be_hidden' => false,
      'can_be_feed_parent' => false,
      'child_count' => 0,
      'broken' => false
    );

    collection('user_categories')->remove(
      array(
        'user_id'     => $this->users->get('_id'),
        'text'        => $screen_name
      ),
      array('justOne' => true)
    );

    $res = collection('user_categories')->save($data);

    unset($data['_id']);
    unset($data['user_id']);

    return $res ? $data : false;
  }

  public function add_twitter_person($category_id, $data = array())
  {
    return $this->_add_feed($category_id, array(
      'text' => '@' . $data['screen_name'],
      'sub_text' => $data['name'],
      'type' => 'twitter_user',
      'can_be_deleted' => false,
      'can_be_hidden' => true,
      'external_id' => $data['id'],
      'external_key' => strtolower($data['screen_name'])
    ));
  }

  public function add_feed($category_id, $title, $url)
  {
    return $this->_add_feed($category_id, ['text' => $title, 'sub_text' => $url]);
  }

  private function _add_feed($category_id, $data = array())
  {
    $data['text'] = strip_tags($data['text']);
    $data['type'] = isset($data['type']) ? $data['type'] : 'feed';

    $external_id = isset($data['external_id']) ? $data['external_id'] : FALSE;

    $this->load->model('model_feeds', 'feeds');
    $feed_id = $this->feeds->save($data['type'], $data['text'], $data['sub_text'], $external_id)->{'$id'};

    if (!$feed_id) return false;

    $exist = collection('category_children')->count([
      'id' => $feed_id,
      'category_id' => $category_id
    ]);

    $data = [
      'id' => $feed_id,
      'category_id' => $category_id,
      'user_id' => $this->users->id(),
      'type' => $data['type'],
      'source_uri' => $data['type'] . ':' . $feed_id,
      'text' => $data['text'],
      'sub_text' => $data['sub_text'],
      'can_be_renamed' => false,
      'can_be_deleted' => isset($data['can_be_deleted']) ? $data['can_be_deleted'] : false,
      'can_be_hidden' => isset($data['can_be_hidden']) ? $data['can_be_hidden'] : false,
      'can_be_feed_parent' => false,
      'broken' => false,
      'external_key' => isset($data['external_key']) ? $data['external_key'] : strtolower($data['text'])
    ];

    if (!$exist)
    {
      $res = collection('category_children')->insert($data);
    }

    // Updates the feed ids inside collections that selected this category
    $collections = collection('collections')->find(array(
      'user.id' => $this->users->get('_id'),
      'sources' => 'category:' . $category_id
      ),
      array(
        'private_id' => true,
        'sources' => true,
        'filters' => true
      )
    );

    if ($collections->count())
    {
      $this->load->model('model_collections', 'collections');

      foreach ($collections as $collection)
      {
        $this->collections->update($collection['private_id'], $collection);
      }
    }

    return $data;
  }

  public function delete($node_id)
  {
    $node = collection('category_children')->findOne(
      array(
        'id' => $node_id,
        'user_id' => $this->users->id()
      ),
      array(
        '_id' => true,
        'category_id' => true
      )
    );

    if ($node)
    {
      $res = collection('category_children')->remove(['_id' => $node['_id']]);

      if ($res)
      {
        collection('user_categories')->update(
          [
            'user_id' => $this->users->id(),
            'id' => $node['category_id']
          ],
          [
            '$inc' => ['child_count' => -1]
          ]
        );
      }
    }
    else
    {
      $res = collection('user_categories')->remove(
        array(
          'user_id' => $this->users->get('_id'),
          'id' => $node_id
        ),
        array(
          'justOne' => true
        )
      );
    }

    return $res ? true : false;
  }

  public function rename_category($category_id, $new_name)
  {
    $res = collection('user_categories')->update(
      array(
        'user_id' => $this->users->get('_id'),
        'id' => $category_id
      ),
      array(
        '$set' => ['text' => strip_tags($new_name)]
      )
    );

    return $res ? true : false;
  }

  public function get_user_categories($include_children = true)
  {
    $res = iterator_to_array(collection('user_categories')->find(
      array('user_id' => $this->users->get('_id')),
      array('_id' => false, 'user_id' => false)
    )->sort(array('text' => 1)), false);

    $data = array(
      'twitter' => array(),
      'feed' => array()
    );

    foreach ($res as &$category)
    {
      $this->_fill_category_with_children($category);

      if ($category['type'] == 'twitter_account') $data['twitter'][] = $category;
      else $data['feed'][] = $category;
    }

    unset($category);

    return $data;
  }

  public function get_user_folder_by_source_id($source_id)
  {
    return collection('user_categories')->findOne(
      array(
        'source_uri' => $source_id,
        'user_id' => $this->users->get('_id')
      ),
      array('_id' => false, 'user_id' => false)
    );
  }

  public function tree($sources = array(), $return_only_ids = false)
  {
    if (!is_array($sources) || count($sources) == 0)
    {
      return array();
    }

    $categories = [];
    $feeds = [];

    foreach ($sources as &$source) {
      list($type, $id) = explode(':', $source);

      switch ($type) {
        case 'feed':
        case 'twitter_user':
          $feeds[] = $id;
          break;

        case 'category':
        case 'twitter_account':
          $categories[] = $id;
          break;
      }
    }

    unset($source);

    if ($return_only_ids)
    {
      if (count($categories) == 0)
      {
        // When no folders are selected, it's not necessary to run all the code below
        // as we already know all the ids
        $nodes = [];

        foreach ($feeds as &$feed)
        {
          $nodes[] = str_replace(['feed:', 'twitter_user:'], '', $feed);
        }

        unset($feed);

        return $nodes;
      }

      $items = collection('category_children')->find(
        [
          '$or' => [
            [
              'category_id' => [
                '$in' => $categories
              ]
            ],
            [
              'id' => [
                '$in' => $feeds
              ]
            ]
          ]
        ],
        [
          'id' => true
        ]
      );

      $nodes = [];

      foreach ($items as $item)
      {
        $nodes[] = $item['id'];
      }

      return $nodes;
    }
    else
    {
      $folders = iterator_to_array(collection('user_categories')->find(
        array(
          'user_id' => $this->users->get('_id'),
          'id' => [
            '$in' => $categories
          ]
        ),
        array(
          '_id' => false,
          'id' => true,
          'type' => true,
          'text' => true,
          'source_uri' => true,
          'child_count' => true,
          'broken' => true,
          'can_be_deleted' => true
        )
      ));

      $data = [
        'twitter' => [],
        'feed' => []
      ];

      foreach ($folders as &$category)
      {
        $this->_fill_category_with_children($category);

        $data[ ($category['type'] == 'feed_category' ? 'feed' : 'twitter') ][] = $category;
      }

      return $data;
    }
  }

  private function _fill_category_with_children(&$category)
  {
    $category['children'] = iterator_to_array(collection('category_children')->find(
      ['category_id' => $category['id']],
      ['_id' => false, 'category_children' => false]
    )->sort(['external_key' => 1]));
  }

  public function tree_ids()
  {
    $res = collection('category_children')->find(
      array(
        'user_id' => $this->users->get('_id')
      ),
      array(
        'id' => true
      )
    );

    $ids = [];

    foreach ($res as $item)
    {
      $ids[] = $item['id'];
    }

    return $ids;
  }
}