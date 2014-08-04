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
    $id = newid();

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

  public function add_feed($category_id, $title, $url)
  {
    $title = strip_tags($title);

    $this->load->model('model_feeds', 'feeds');
    $feed_id = $this->feeds->save($title, $url)->{'$id'};

    if (!$feed_id) return false;

    $data = [
      'id' => $feed_id,
      'type' => 'feed',
      'source_uri' => 'feed:' . $feed_id,
      'text' => $title,
      'sub_text' => $url,
      'can_be_renamed' => false,
      'can_be_deleted' => true,
      'can_be_hidden' => false,
      'can_be_feed_parent' => false,
      'broken' => false
    ];

    $res = collection('user_categories')->update(
      array(
        'user_id' => $this->users->get('_id'),
        'id' => $category_id
      ),
      array(
        '$push' => [
          'children' => $data
        ],
        '$inc' => [
          'child_count' => 1
        ]
      )
    );

    return $res ? $data : false;
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
        'children.id' => $node_id
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

  public function get_user_feed_categories()
  {
    return iterator_to_array(collection('user_categories')->find(
      array('user_id' => $this->users->get('_id')),
      array('_id' => false, 'user_id' => false)
    ), false);
  }

  public function tree($sources = array())
  {
    $categories = [];
    $feeds = [];

    foreach ($sources as $source) {
      if (strpos($source, 'category:') === 0) array_push($categories, str_replace('category:', '', $source));
      else if (strpos($source, 'feed:') === 0) array_push($feeds, str_replace('feed:', '', $source));
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
      array(
        '_id' => false,
        'id' => true,
        'type' => true,
        'text' => true,
        'source_uri' => true,
        'child_count' => true,
        'broken' => true,
        'children' => [
          '$elemMatch' => [
            'id' => [
              '$in' => $feeds
            ]
          ]
        ],
        'children.id' => true,
        'children.text' => true,
        'children.type' => true,
        'children.source_uri' => true
      )
    );

    return iterator_to_array($res, false);
  }
}