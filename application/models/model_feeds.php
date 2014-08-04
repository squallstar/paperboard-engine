<?php
/**
 * feed model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_feeds extends CI_Model
{
  public function save($title, $url)
  {
    $feed = collection('feeds')->findAndModify(
      array('url' => $url),
      array(
        '$inc' => [
          'added_count' => 1
        ]
      ),
      array('_id' => true)
    );

    if (!$feed)
    {
      $data = array(
        'title' => $title,
        'url' => $url,
        'processed_at' => 0,
        'failed_count' => 0,
        'added_count' => 1
      );

      $res = collection('feeds')->insert($data);

      if ($res)
      {
        return $data['_id'];
      }
    } else {
      return $feed['_id'];
    }
  }

  public function decrement_count($id)
  {
    collection('feeds')->update(
      array('_id' => $id),
      array(
        '$inc' => [
          'added_count' => -1
        ]
      )
    );
  }
}