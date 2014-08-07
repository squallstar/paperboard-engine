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

  public function save($type, $title, $url)
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
        'type' => $type,
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
        'type' => 'feed',
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

  public function update_twitter_followers($user_id = FALSE, $timespan = 1)
  {
    ini_set("memory_limit","128M");

    $cond = array(
      'connected_accounts.type' => 'twitter',
      'connected_accounts.following.updated_at' => ['$lt' => $timespan]
    );

    if ($user_id !== FALSE) $cond['_id'] = $user_id;

    $users = collection('users')->find(
      $cond,
      array(
        '_id' => true,
        'connected_accounts' => true,
      )
    );

    if ($user_id) $users->limit(1);

    if (!$users->count()) return FALSE;

    $count = 0;

    $this->load->library('twitter');
    $this->load->model('model_users', 'users');
    $this->load->model('model_sources', 'sources');

    foreach ($users as $user)
    {
      $this->users->set_user($user);

      foreach ($user['connected_accounts'] as $account)
      {
        if ($account['type'] != 'twitter') continue;

        $this->twitter->set_local_token($account['access_token']);

        $folder = $this->sources->get_user_folder_by_source_id($account['id']);

        if ($folder)
        {
          $friends = $this->twitter->get_friends();

          foreach ($friends as &$friend)
          {
            if ($this->sources->add_twitter_person($folder['id'], $friend))
            {
              $count++;
            }
          }

          unset($friend);
          unset($friends);
        }

        unset($folder);
      }
    }

    return $count;
  }
}