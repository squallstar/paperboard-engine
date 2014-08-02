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
      'text'        => $name,
      'type'        => 'feed_category',
      'source_uri'  => 'category:' . $id,
      'user_id'     => $this->users->get('_id')
    );

    $res = collection('user_categories')->save($data);

    unset($data['_id']);
    unset($data['user_id']);

    return $res ? $data : false;
  }

  public function get_user_feed_categories()
  {
    return iterator_to_array(collection('user_categories')->find(
      array('user_id' => $this->users->get('_id')),
      array('_id' => false, 'user_id' => false)
    ));
  }
}