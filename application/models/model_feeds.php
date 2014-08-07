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
  private $_is_downloading;

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
        'type' => 'feed',
        'processed_at' => 0,
        'failed_count' => 0,
        'added_count' => 1,
        'articles_count' => 0
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

  public function download($limit = 30)
  {
    if ($this->_is_downloading) return;

    $feeds = collection('feeds')->find(
      array(
        'processed_at' => [
          '$lt' => time() - 3600
        ],
        'failed_count' => [
          '$lt' => 5
        ]
      ),
      array(
        'url' => true,
        'type' => true
      )
    )->limit($limit)->sort(array('processed_at' => 1));

    $feeds = iterator_to_array($feeds, false);
    $count = count($feeds);

    if ($count == 0) {
      unset($feeds);
      return FALSE;
    }

    $this->load->model('model_articles_downloader', 'downloader');

    $this->_is_downloading = true;

    $this->downloader->update_sources($feeds);

    $this->_is_downloading = false;

    unset($feeds);
    return $count == $limit;
  }
}