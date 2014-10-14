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
  const FOLLOWERS_OUTDATED_SECONDS = 600;

  private $_is_downloading;

  public function save($type, $title, $url, $external_id = null)
  {
    $feed = collection('feeds')->findAndModify(
      array('type' => $type, 'url' => $url),
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
          '$lt' => time() - 1800
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

    $this->load->model('model_feeds_downloader', 'downloader');

    $this->_is_downloading = true;

    $this->downloader->update_sources($feeds);

    $this->_is_downloading = false;

    unset($feeds);
    return $count == $limit;
  }

  public function update_twitter_followers($user_id = FALSE)
  {
    set_time_limit(0);
    ini_set("memory_limit","128M");

    $cond = array(
      'connected_accounts.type' => 'twitter',
      'connected_accounts.following.updated_at' => ['$lt' => time() - self::FOLLOWERS_OUTDATED_SECONDS]
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

  public function update_instagram_followers($user_id = FALSE)
  {
    set_time_limit(0);
    ini_set("memory_limit","128M");

    $cond = array(
      'connected_accounts.type' => 'instagram',
      'connected_accounts.following.updated_at' => ['$lt' => time() - self::FOLLOWERS_OUTDATED_SECONDS]
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

    $this->load->library('instagram');
    $this->load->model('model_users', 'users');
    $this->load->model('model_sources', 'sources');

    foreach ($users as $user)
    {
      $this->users->set_user($user);

      foreach ($user['connected_accounts'] as $account)
      {
        if ($account['type'] != 'instagram') continue;

        $this->instagram->setAccessToken($account['access_token']['oauth_token']);

        $folder = $this->sources->get_user_folder_by_source_id($account['id']);

        $friends_count = 0;

        if ($folder)
        {
          $friends = $this->instagram->getUserFollows('self', 500);

          if ($friends)
          {
            $friends_count = count($friends);

            foreach ($friends as &$friend)
            {
              if ($this->sources->add_instagram_person($folder['id'], $friend))
              {
                $count++;
              }
            }

            unset($friend);
          }

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
              'connected_accounts.$.following.updated_at' => time(),
              'connected_accounts.$.following.count' => $friends_count
            )
          )
        );

        unset($friends_count);
      }
    }

    return $count;
  }

  public function download_tweets()
  {
    $ts = time() - 65;

    $users = collection('users')->find(
      array(
        'connected_accounts' => [
          '$elemMatch' => [
            'type' => 'twitter',
            'processed_at' => ['$lt' => $ts]
          ]
        ]
      ),
      array(
        '_id' => true,
        'connected_accounts' => true,
      )
    )->limit(30);

    if ($users->count() == 0) return 0;

    $added = 0;

    $sources = [];

    $this->load->library('twitter');
    $this->load->model('model_users', 'users');
    $this->load->model('model_sources', 'sources');

    foreach ($users as $user)
    {
      _log("Checking connected accounts for user " . $user['_id']);

      $this->users->set_user($user);

      foreach ($user['connected_accounts'] as $account)
      {
        if ($account['type'] != 'twitter' || $account['processed_at'] > $ts) continue;

        _log("Downloading tweets for account " . $account['id'] . " (" . $account['screen_name'] . ")");

        $folder = $this->sources->get_user_folder_by_source_id($account['id']);

        $this->twitter->set_local_token($account['access_token']);

        $tweets = $this->twitter->get_tweets();

        $count = count($tweets);

        if (is_array($tweets) && $count)
        {
          _log("Processing " . count($tweets) . " tweets.");

          foreach ($tweets as &$tweet)
          {
            $ex_id = $tweet['sources'][0]['external_id'];

            if (!count($sources) || !isset($sources[$ex_id]))
            {
              // Find source on db
              $source = collection('feeds')->findOne(
                array('type' => 'twitter_user', 'external_id' => $ex_id),
                array('_id' => true)
              );

              _log("twitter source exist? " . $ex_id . ', ' . ($source ? 'yes' : 'no'));

              if (!$source)
              {
                // Adds the person as a new source
                $source = $this->sources->add_twitter_person($folder['id'], array(
                  'id' => $ex_id,
                  'name' => $tweet['sources'][0]['full_name'],
                  'screen_name' => $tweet['sources'][0]['screen_name'],
                  'avatar' => $tweet['sources'][0]['profile_image_url']
                ));

                _log("New twitter source added: " . $tweet['sources'][0]['screen_name']);
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
            $added++;
          }

          _log("Downloaded " . $added . " new tweets for user " . $user['_id']);
        }

        unset($tweet);

        if (is_array($tweets) && $count > 0)
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

  public function download_instagram_pics()
  {
    $ts = time() - 180;

    $users = collection('users')->find(
      array(
        'connected_accounts' => [
          '$elemMatch' => [
            'type' => 'instagram',
            'processed_at' => ['$lt' => $ts]
          ]
        ]
      ),
      array(
        '_id' => true,
        'connected_accounts' => true,
      )
    )->limit(30);

    if ($users->count(true) == 0) return 0;

    $added = 0;

    $sources = [];

    $this->load->library('instagram');
    $this->load->model('model_users', 'users');
    $this->load->model('model_sources', 'sources');

    foreach ($users as $user)
    {
      _log("Checking connected instagram accounts for user " . $user['_id']);

      $this->users->set_user($user);

      foreach ($user['connected_accounts'] as $account)
      {
        if ($account['type'] != 'instagram' || $account['processed_at'] > $ts) continue;

        _log("Downloading instagram pics for account " . $account['id'] . " (" . $account['screen_name'] . ")");

        $folder = $this->sources->get_user_folder_by_source_id($account['id']);

        $this->instagram->setAccessToken($account['access_token']['oauth_token']);

        $pics = $this->instagram->getPics($account['processed_at'] == 0 ? 150 : 30);

        $count = count($pics);

        if (is_array($pics) && $count)
        {
          _log("Processing " . count($pics) . " pics.");

          foreach ($pics as &$pic)
          {
            $ex_id = $pic['sources'][0]['external_id'];

            if (!count($sources) || !in_array($ex_id, array_keys($sources)))
            {
              // Find source on db
              $source = collection('feeds')->findOne(
                array('type' => 'instagram_user', 'external_id' => $ex_id),
                array('_id' => true)
              );

              if (!$source)
              {
                // Adds the person as a new source
                $source = $this->sources->add_instagram_person($folder['id'], array(
                  'id' => $ex_id,
                  'full_name' => $pic['sources'][0]['full_name'],
                  'username' => $pic['sources'][0]['screen_name'],
                  'profile_picture' => $pic['sources'][0]['profile_image_url']
                ));

                _log("New instagram source added: " . $pic['sources'][0]['screen_name']);
              }
              else
              {
                // Existing
                $source['id'] = $source['_id']->{'$id'};
              }

              // Source setup
              $source['added'] = 0;
              $sources[$ex_id] = $source;

              $pic['source'] = $source['id'];
            }
            else
            {
              $pic['source'] = $sources[$ex_id]['id'];
            }

            $sources[$ex_id]['added']++;
            $added++;
          }

          _log("Downloaded " . $added . " instagram new pics for user " . $user['_id']);
        }

        unset($pic);

        if (is_array($pics) && $count > 0)
        {
          try
          {
            collection('articles')->batchInsert($pics);
            $added += $count;
          }
          catch (Exception $e)
          {
            log_message('error', $e->getMessage());
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

  public function cleanup_old_articles($limit = 100, $days = 40)
  {
    $ts = strtotime('-' . intval($days) . ' days', time());

    $articles = collection('articles')->find(
      ['processed_at' => ['$lt' => $ts]],
      [
        '_id' => 1,
        'name' => 1,
        'has_image' => 1,
        'images_processed' => 1,
        'lead_image' => 1
      ]
    )->limit($limit);

    $i = 0;

    foreach ($articles as $article)
    {
      if ($this->purge_article($article, true)) $i++;
    }

    return $i;
  }

  public function cleanup_unused_articles($feeds_limit = 50, $articles_limit = 100)
  {
    $this->load->model('model_images_processor', 'images');

    $data = [
      'feeds' => 0,
      'articles' => 0
    ];

    foreach (collection('feeds')->find([], ['_id' => 1])->limit($feeds_limit) as $feed)
    {
      $feed_id = $feed['_id']->{'$id'};

      if (collection('category_children')->count(['feed_id' => $feed_id]) == 0)
      {
        $count = collection('articles')->count(['source' => $feed_id, 'has_image' => false]);

        if ($count)
        {
          if (collection('articles')->remove(['source' => $feed_id, 'has_image' => false]))
          {
            $data['articles'] += $count;
          }
        }

        foreach (collection('articles')->find(
          [
            'source' => $feed_id
          ],
          [
            '_id' => 1,
            'has_image' => 1,
            'images_processed' => 1,
            'lead_image' => 1
          ])->limit($articles_limit) as $article)
        {
          if ($this->purge_article($article)) $data['articles']++;
        }

        if (collection('articles')->count(['source' => $feed_id]) == 0)
        {
          if (collection('feeds')->remove(['_id' => $feed['_id']], ['justOne' => 1])) $data['feeds']++;
        }
      }
    }

    return $data;
  }

  public function purge_article(&$article, $force = false)
  {
    $done = true;

    if (isset($article['lead_image']))
    {
      $done = $this->images->delete_asset($article['lead_image']);
    }

    if ($done || $force)
    {
      return collection('articles')->remove(['_id' => $article['_id']], ['justOne' => true]);
    }

    return false;
  }
}