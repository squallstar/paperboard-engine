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

  public function save($type, $title, $url, $external_id = null)
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
        'external_id' => $external_id,
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

        collection('users')->update(
          array(
            '_id' => $user['_id'],
            'connected_accounts.id' => $account['id']
          ),
          array(
            '$set' => array(
              'connected_accounts.$.following.updated_at' => time()
            )
          )
        );
      }
    }

    return $count;
  }

  public function download_tweets()
  {
    $users = collection('users')->find(
      array(
        'connected_accounts.type' => 'twitter',
        'connected_accounts.processed_at' => ['$lt' => time() - 120]
      ),
      array(
        '_id' => true,
        'connected_accounts' => true,
      )
    )->limit(40);

    if ($users->count() == 0) return 0;

    _log("Started to download tweets for " . count($users->count()) . " users");

    $added = 0;

    $sources = [];

    $this->load->library('twitter');
    $this->load->model('model_users', 'users');
    $this->load->model('model_sources', 'sources');

    foreach ($users as $user)
    {
      $this->users->set_user($user);

      foreach ($user['connected_accounts'] as $account)
      {
        if ($account['type'] != 'twitter') continue;

        $folder = $this->sources->get_user_folder_by_source_id($account['id']);

        $this->twitter->set_local_token($account['access_token']);

        $tweets = $this->twitter->get_tweets();

        foreach ($tweets as &$tweet)
        {
          $ex_id = $tweet['sources'][0]['external_id'];

          if (!count($sources) || !in_array($ex_id, array_keys($sources)))
          {
            // Find source on db
            $source = collection('feeds')->findOne(
              array('external_id' => $ex_id),
              array('_id' => true)
            );

            if (!$source)
            {
              // Adds the person as a new source
              $source = $this->sources->add_twitter_person($folder['id'], array(
                'id' => $ex_id,
                'name' => $tweet['sources'][0]['full_name'],
                'screen_name' => $tweet['sources'][0]['screen_name'],
                'avatar' => $tweet['sources'][0]['profile_image_url']
              ));
            }
            else
            {
              // Existing
              $source['id'] = $source['_id']->{'$id'};
            }

            // Source setup
            $source['added'] = 0;
            $sources[$ex_id] = $source;

            $tweet['source'] = $source['id'];
          }
          else
          {
            $tweet['source'] = $sources[$ex_id]['id'];
          }

          $sources[$ex_id]['added']++;
        }

        unset($tweet);

        $count = count($tweets);

        _log("Downloaded " . $added . " new tweets for user " . $user['_id']);

        if ($count > 0)
        {
          try
          {
            collection('articles')->batchInsert($tweets);
            $added += $count;
          }
          catch (Exception $e)
          {
            log_message('error', $e->getMessage());
          }
        }
      }

      collection('users')->update(
        array(
          '_id' => $user['_id'],
          'connected_accounts.id' => $account['id']
        ),
        array(
          '$set' => array(
            'connected_accounts.$.processed_at' => time()
          )
        )
      );
    }

    foreach ($sources as &$source)
    {
      collection('feeds')->update(
        array('_id' => $source['id']),
        array(
          '$inc' => array(
            'articles_count' => $source['added']
          )
        )
      );
    }

    unset($source);

    return $added;
  }
}