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
  const MAX_CHILDREN_FOR_CATEGORY = 1000;

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

  public function add_instagram_category($id, $screen_name, $full_name = '')
  {
    $data = array(
      'id'          => $id,
      'text'        => $screen_name,
      'sub_text'    => $full_name,
      'type'        => 'instagram_account',
      'source_uri'  => 'instagram_account:' . $id,
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
        'text'        => $screen_name,
        'type'        => 'instagram_account'
      ),
      array('justOne' => true)
    );

    $res = collection('user_categories')->save($data);

    unset($data['_id']);
    unset($data['user_id']);

    return $res ? $data : false;
  }

  public function add_instagram_person($category_id, $data)
  {
    if (is_array($data))
    {
      $data = (object) $data;
    }

    return $this->_add_feed($category_id, array(
      'text' => $data->username,
      'sub_text' => $data->full_name,
      'type' => 'instagram_user',
      'can_be_deleted' => false,
      'can_be_hidden' => true,
      'external_id' => intval($data->id),
      'external_key' => strtolower($data->username),
      'avatar' => $data->profile_picture
    ));
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
        'text'        => $screen_name,
        'type'        => 'twitter_account'
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


    $exist = collection('category_children')->findOne([
      'feed_id' => $feed_id,
      'category_id' => $category_id
    ], ['id' => 1, 'source_uri' => 1]);

    $data = [
      'feed_id' => $feed_id,
      'category_id' => $category_id,
      'user_id' => $this->users->id(),
      'type' => $data['type'],
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
      $data['id'] = newid('f-');
      $data['source_uri'] = $data['type'] . ':' . $data['id'];
      $res = collection('category_children')->insert($data);
    }
    else
    {
      $data['id'] = $exist['id'];
      $data['source_uri'] = $exist['source_uri'];
    }

    unset($exist);

    // Updates the feed ids inside collections that selected this category
    $collections = collection('collections')->find(array(
      'user.id' => $this->users->get('_id'),
      'sources' => $data['source_uri']
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

  public function find_node_by_id($node_id)
  {
    return collection('category_children')->findOne(
      array(
        'id' => $node_id,
        'user_id' => $this->users->id()
      ),
      array(
        '_id' => true,
        'category_id' => true
      )
    );
  }

  public function move_node($node_id, $new_category_id)
  {
    $node = $this->find_node_by_id($node_id);

    if ($node)
    {
      $category = collection('user_categories')->findOne(
        [
          'id' => $new_category_id,
          'user_id' => $this->users->id()
        ],
        ['id' => 1]
      );

      if ($category)
      {
        try
        {
          $res = collection('category_children')->update(
            ['id' => $node_id],
            ['$set' => ['category_id' => $new_category_id]]
          ) ? true : false;

          collection('user_categories')->update(
            ['id' => $node['category_id']],
            ['$inc' => ['added_count' => -1]]
          );

          collection('user_categories')->update(
            ['id' => $new_category_id],
            ['$inc' => ['added_count' => 1]]
          );

          return $res;
        }
        catch (Exception $e)
        {
          log_message('error', 'Moving node ' . $node_id . ' to folder ' . $new_category_id . ': ' . $e->getMessage());
          return false;
        }
      }
    }

    return false;
  }

  public function delete($node_id)
  {
    $node = $this->find_node_by_id($node_id);

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
          'user_id' => $this->users->id(),
          'id' => $node_id
        ),
        array(
          'justOne' => true
        )
      );

      collection('category_children')->remove(
        array(
          'user_id' => $this->users->id(),
          'category_id' => $node_id
        ),
        array(
          'justOne' => false
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

      if ($category['type'] == 'twitter_account' || $category['type'] == 'instagram_account') $data['twitter'][] = $category;
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
      if (strpos($source, ':') === FALSE) continue;

      list($type, $id) = explode(':', $source);

      switch ($type) {
        case 'category':
        case 'twitter_account':
        case 'instagram_account':
        case 'feedly_account':
          $categories[] = $id;
          break;

        default:
          $feeds[] = $source;
          break;
      }
    }

    unset($source);

    if ($return_only_ids)
    {
      $nodes = [];

      if (count($categories) == 0)
      {
        $cond = [
          'source_uri' => [
            '$in' => $feeds
          ]
        ];
      }
      else
      {
        $cond = [
          '$or' => [
            [
              'category_id' => [
                '$in' => $categories
              ]
            ],
            [
              'source_uri' => [
                '$in' => $feeds
              ]
            ]
          ]
        ];
      }

      $items = collection('category_children')->find(
        $cond,
        [
          'feed_id' => true
        ]
      );

      foreach ($items as $item)
      {
        $nodes[] = $item['feed_id'];
      }

      return $nodes;
    }
    else
    {
      $folders = iterator_to_array(collection('user_categories')->find(
        array(
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
      }

      if (count($feeds))
      {
        // Some feeds were selected. we need to find out their folders
        $cats = [];

        $items = collection('category_children')->find(
          [
            'source_uri' => [
              '$in' => $feeds
            ]
          ],
          [
            '_id' => false,
            'feed_id' => false
          ]
        );

        $not_extracted_cats = [];

        foreach ($items as $item)
        {
          $cat_id = $item['category_id'];
          unset($item['category_id']);

          if (isset($cats[$cat_id]))
          {
            $cats[$cat_id]['children'][] = $item;
          }
          else
          {
            // Folder not extracted yet. Add to next queue

            if (!isset($not_extracted_cats[$cat_id]))
            {
              $not_extracted_cats[$cat_id] = [];
            }

            $not_extracted_cats[$cat_id][] = $item;
          }
        }

        // Next. extract missing categories
        $ids = array_keys($not_extracted_cats);

        if (count($ids))
        {
          $feed_cats = iterator_to_array(collection('user_categories')->find(
            array(
              'id' => [
                '$in' => $ids
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

          foreach ($feed_cats as $folder)
          {
            $cats[$folder['id']] = $folder;
            $cats[$folder['id']]['children'] = $not_extracted_cats[$folder['id']];
          }
        }

        foreach ($cats as $id => &$cat) {
          $folders[] = $cat;
        }

        unset($cats);
        unset($not_extracted_cats);
        unset($items);
      }

      $data = [
        'twitter' => [],
        'feed' => []
      ];

      foreach ($folders as &$category)
      {
        $data[ ($category['type'] == 'feed_category' ? 'feed' : 'twitter') ][] = $category;
      }

      unset($category);
      unset($folders);

      return $data;
    }
  }

  private function _fill_category_with_children(&$category)
  {
    $category['children'] = iterator_to_array(collection('category_children')->find(
      ['category_id' => $category['id']],
      ['_id' => false, 'category_id' => false, 'feed_id' => false]
    )->limit(self::MAX_CHILDREN_FOR_CATEGORY)->sort(['external_key' => 1]));
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

  public function purge_category_children($sources)
  {
    $n = 0;

    foreach ($sources as $source)
    {
      $feed = collection('feeds')->findOne(['_id' => $source['feed_id']], ['_id' => 1, 'added_count' => 1]);

      if ($feed['added_count'] <= 1)
      {
        $n++;

        collection('feeds')->remove(['_id' => $source['feed_id']], ['justOne' => true]);
        collection('articles')->remove(['source' => $feed['_id']]);
      }
    }

    return $n;
  }
}