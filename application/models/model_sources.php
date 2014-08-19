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

  public function reorder_category_children($category_id, $key = 'text')
  {
    $order = [];
    $order[$key] = 1;

    return collection('user_categories')->update(
      array(
        'user_id' => $this->users->get('_id'),
        'id' => $category_id
      ),
      array(
        '$push' => [
          'children' => [
            '$each' => [],
            '$sort' => $order
          ]
        ]
      ),
      array(
        'w' => 0
      )
    );
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
      'broken' => false,
      'children' => []
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
      'broken' => false,
      'children' => []
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

    $exist = collection('user_categories')->count([
      'id' => $category_id,
      'children.id' => $feed_id
    ]);

    $data = [
      'id' => $feed_id,
      'type' => $data['type'],
      'source_uri' => $data['type'] . ':' . $feed_id,
      'text' => $data['text'],
      'sub_text' => $data['sub_text'],
      'can_be_renamed' => false,
      'can_be_deleted' => isset($data['can_be_deleted']) ? $data['can_be_deleted'] : false,
      'can_be_hidden' => isset($data['can_be_hidden']) ? $data['can_be_hidden'] : false,
      'can_be_feed_parent' => false,
      'broken' => false,
      'external_key' => isset($data['external_key']) ? $data['external_key'] : null
    ];

    if (!$exist)
    {
      $res = collection('user_categories')->update(
        array(
          //'user_id' => $this->users->get('_id'),
          'id' => $category_id
        ),
        array(
          '$push' => [
            'children' => $data
          ],
          '$inc' => [
            'child_count' => 1
          ]
        ),
        array(
          'w' => 0
        )
      );
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
    $res1 = collection('user_categories')->remove(
      array(
        'user_id' => $this->users->get('_id'),
        'id' => $node_id
      ),
      array(
        'justOne' => true
      )
    );

    $res2 = collection('user_categories')->update(
      array(
        'user_id' => $this->users->get('_id'),
        'children' => array(
          '$elemMatch' => array(
            'id' => $node_id
          )
        )
      ),
      array(
        '$pull' => array(
          'children.id' => $node_id
        ),
        // TODO: update child_count
      )
    );

    return $res1 && $res2 ? true : false;
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

  // public function get_user_feed_categories()
  // {
  //   return iterator_to_array(collection('user_categories')->find(
  //     array('user_id' => $this->users->get('_id')),
  //     array('_id' => false, 'user_id' => false)
  //   ), false);
  // }

  public function get_user_categories()
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

        foreach ($feeds as &$feed) {
          $nodes[] = str_replace(['feed:', 'twitter_user:'], '', $feed);
        }

        unset($feed);

        return $nodes;
      }

      $fields = array(
        '_id' => false,
        'id' => true,
        'type',
        'children.id' => true
      );
    }
    else
    {
      $fields = array(
        '_id' => false,
        'id' => true,
        'type' => true,
        'text' => true,
        'source_uri' => true,
        'child_count' => true,
        'broken' => true,
        'can_be_deleted' => true,
        'children.id' => true,
        'children.text' => true,
        'children.type' => true,
        'children.source_uri' => true,
        'children.sub_text' => true,
        'children.can_be_deleted' => true,
        'children.can_be_renamed' => true,
        'children.can_be_feed_parent' => true,
        'children.can_be_hidden' => true,
        'children.broken' => true
      );
    }

    $res = collection('user_categories')->find(
      array(
        'user_id' => $this->users->get('_id'),
        '$or' => [
          [
            'id' => [
              '$in' => $categories
            ]
          ],
          [
            'children.id' => [
              '$in' => $feeds
            ]
          ]
        ]
      ),
      $fields
    );

    $nodes = array();

    if ($return_only_ids)
    {
      foreach ($res as $category)
      {
        if (in_array($category['id'], $categories))
        {
          // Folder is fully selected
          foreach ($category['children'] as $feed) $nodes[] = $feed['id'];
        }
        else
        {
          foreach ($category['children'] as $feed)
          {
            if (in_array($feed['id'], $feeds))
            {
              $nodes[] = $feed['id'];
            }
          }
        }
      }

      return $nodes;
    }
    else
    {
      $data = [
        'twitter' => [],
        'feed' => []
      ];

      foreach ($res as $category)
      {
        if (!in_array($category['id'], $categories))
        {
          //Filters children when category was not fully selected
          $category['children'] = array_values(array_filter($category['children'], function($feed) use($feeds)
          {
            return in_array($feed['id'], $feeds);
          }));
        }
        else
        {
          $category['children'] = [];
        }

        if ($category['type'] == 'feed_category') $data['feed'][] = $category;
        else $data['twitter'][] = $category;
      }

      return $data;
    }
  }

  public function tree_ids()
  {
    $res = collection('user_categories')->find(
      array(
        'user_id' => $this->users->get('_id')
      ),
      array(
        'children.id'
      )
    );

    $ids = [];

    foreach ($res as $category)
    {
      foreach ($category['children'] as $feed)
      {
        $ids[] = $feed['id'];
      }
    }

    return $ids;
  }
}