<?php
/**
 * feeds downloader model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Model_feeds_downloader extends CI_Model
{
  const ARTICLE_CONTENT_LENGTH = 300;

  public function update_sources($sources = array())
  {
    _log("Started to download " . count($sources) . " sources");

    // multi curl
    $mh = curl_multi_init();
    $ch = array();

    foreach ($sources as $source)
    {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $this->_g_url($source));
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, 10);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

      $ch[$source['_id']->{'$id'}] = $curl;
      curl_multi_add_handle($mh, $curl);
    }

    if (count($ch))
    {
      $active = 1;

      do
      {
        curl_multi_exec($mh, $active);
      } while ($active > 0);
    }

    foreach ($sources as &$source)
    {
      $id = $source['_id']->{'$id'};

      if (isset($ch[$id]))
      {
        $curlError = curl_error($ch[$id]);

        if($curlError == "")
        {
          $source['raw_data'] = curl_multi_getcontent($ch[$id]);
        }
      }

      $this->update_source($source);
    }

    unset($ch);
    unset($sources);
  }

  public function update_source($source)
  {
    if (isset($source['type']) && $source['type'] == 'twitter_account')
    {
      // TODO
      // $this->load->model('model_twitter');
      // $entries = $this->model_twitter->get_feed($source);
    }
    else
    {
      $entries = $this->_get_feed_rss($source);
    }

    if ($entries === false)
    {
      //Failed to update
      _log("Failed to update source " . $source['url']);
      return;
    }

    $count = count($entries);

    if ($count)
    {
      //Filter entries that have been already added to the DB
      $this->_filter_entries($entries);

      $count = count($entries);

      if ($count)
      {
        $this->_insert_articles($entries, $source);
      }
    }

    unset($entries);

    _log("Source " . $source['_id']->{'$id'} . " updated with " . $count . " new articles");

    collection('feeds')->update(
      array(
        '_id' => $source['_id']
      ),
      array(
        '$set' => array(
          'processed_at' => time()
        ),
        '$inc' => array(
          'articles_count' => $count
        )
      )
    );
  }

  private function _g_url(&$source)
  {
    return "https://www.google.com/uds/Gfeeds?hl=en&num=50&v=1.0&output=json&q=" . urlencode($source['url']) . "&nocache=" . (time()-3600);
  }

  private function _get_feed_rss(&$source)
  {
    if (isset($source['raw_data']))
    {
      //1.1 content already got from multi curl
      $contents = json_decode($source['raw_data']);
      unset($source['raw_data']);
    }
    else
    {
      //1.2 get feed
      $contents = json_decode(file_get_contents($this->_g_url($source)));
    }

    if (!$contents || $contents->responseStatus != 200)
    {
      $response = isset($contents->responseDetails) ? $contents->responseDetails : $contents;
      $this->_source_failed($source, $response);
      return false;
    }

    //2. normalize data
    $entries = array();

    if (isset($contents->responseData->feed->title))
    {
      $source['title'] = $contents->responseData->feed->title;
    }

    foreach ($contents->responseData->feed->entries as $entry)
    {
      if (!$entry->link)
      {
        continue;
      }

      $entry->link = $this->_strip_utmparams($entry->link);

      if (strlen($entry->link) >= 255)
      {
        continue;
      }

      $entry->hash = md5($entry->link);

      $entries[$entry->hash] = $entry;
    }

    unset($contents);

    return $entries;
  }

  private function _filter_entries(&$entries)
  {
    $dbArticles = collection('articles')->find(
      array(
        'id' => array(
          '$in' => array_keys($entries)
        )
      ),
      array(
        '_id' => false,
        'id'  => true
      )
    );

    if ($dbArticles->count() > 0)
    {
      //At least one article found on DB
      foreach ($dbArticles as $dbArticle)
      {
        //Exists? Skip it
        if (isset($entries[$dbArticle['id']]))
        {
          unset($entries[$dbArticle['id']]);
        }
      }
    }

    unset($dbArticles);
  }

  private function _source_failed($source, $response)
  {
    collection('feeds')->update(
      array(
        '_id' => $source['_id']
      ),
      array(
        '$inc' => array(
          'failed_count' => 1,
          'processed_at' => time()
        )
      )
    );

    log_message('error', 'Model_articles_downloader#update_source ' . $response . ' > ' . json_encode($source));
  }

  private function _insert_articles($entries, $source)
  {
    $batchArticles = array();

    if (!function_exists('images_from_string'))
    {
      $this->load->helper('images');
    }

    switch ($source['type']) {
      case 'twitter_account':
        // $this->load->model('model_twitter');
        // foreach ($entries as $hash => $entry)
        // {
        //   $batchArticles[$hash]= $this->model_twitter->parse_entry($entry, $source);
        // }
        break;

      default:
        $source_pieces = parse_url($source['url']);
        $source['host'] = $source_pieces['scheme'] . '://' . $source_pieces['host'];

        foreach ($entries as $hash => $entry)
        {
          $batchArticles[$hash]= $this->_get_data_for_entry_rss($entry, $source);
        }
    }

    if (count($batchArticles))
    {
      try{
        collection('articles')->batchInsert(array_reverse(array_values($batchArticles)));
      } catch (Exception $e) {
        log_message('error', $e->getMessage());
      }
    }
  }

  public function retrieve_feedproxies($articles = array())
  {
    foreach ($articles as $article)
    {
      $ch = curl_init();
      $ret = curl_setopt($ch, CURLOPT_URL, $article['url']);
      $ret = curl_setopt($ch, CURLOPT_HEADER, 1);
      $ret = curl_setopt($ch, CURLOPT_NOBODY, 1);
      $ret = curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
      $ret = curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $ret = curl_setopt($ch, CURLOPT_TIMEOUT, 10);
      $ret = curl_exec($ch);
      if (!empty($ret))
      {
        $info = curl_getinfo($ch);
        curl_close($ch);
        if (isset($info['redirect_url']))
        {
          $url = $this->_strip_utmparams($info['redirect_url']);

          // We can only have one record with this url
          $n = collection('articles')->find(array('url' => $url))->limit(1)->count();

          if ($n == 0)
          {
            $url_parts = parse_url($url);

            collection('articles')->update(
              array('url' => $url),
              array(
                '$set' => array(
                  'url'      => $url,
                  'url_host' => isset($url_parts['host']) ? $url_parts['host'] : ''
                )
              )
            );
          }
          else
          {
            collection('articles')->delete(
              array('_id' => $article['_id']),
              array('justOne' => true)
            );
          }
        }
      }
    }
  }

  private function _strip_utmparams($string = '')
  {
    return preg_replace('/(\?|\&)?utm_[a-z]+=[^\&]+/', '', $string);
  }

  private function _get_data_for_entry_rss($entry, $source)
  {
    $now = time();

    $url_pieces = parse_url($entry->link);
    $domain = isset($url_pieces['host']) ? $url_pieces['host'] : '';

    $ts = strtotime($entry->publishedDate);

    $content = trim($entry->content);

    $data = array(
      'id'           => $entry->hash,
      'source'       => $source['_id']->{'$id'},
      'sources'      => array(
        array(
          'external_id'  => $source['_id']->{'$id'},
          'full_name'    => $source['title'],
          'published_at' => $now < $ts ? $now : $ts,
          'type'         => 'Feed',
          'screen_name'  => strip_tags($entry->author)
        )
      ),
      'name'         => $entry->title,
      'url'          => $entry->link,
      'description'  => strip_tags($content),
      'content'      => $content,
      'published_at' => $now < $ts ? $now : $ts,
      'processed_at' => $now,
      'url_host'     => $domain,
      'lead_image'   => NULL,
      'assets'       => array(),
      'tags'         => array(),
      'lead_image_in_content' => false,
      'show_external_url' => true,
      'fetched'      => false
    );

    if (count($entry->categories))
    {
      foreach ($entry->categories as $tag)
      {
        $data['tags'][] = array(
          'name' => $tag
        );
      }
    }

    if (strlen($data['description']) >= self::ARTICLE_CONTENT_LENGTH)
    {
      $data['description'] = substr($data['description'], 0, self::ARTICLE_CONTENT_LENGTH-1) . '...';
    }

    $img = images_from_string($entry->content);

    if (!$img && isset($entry->mediaGroups))
    {
      $media = $entry->mediaGroups;
      if (count($media) && isset($media[0]->contents))
      {
        foreach ($media[0]->contents as $file)
        {
          if (isset($file->type))
          {
            if (strpos($file->type, 'image') !== FALSE)
            {
              $img = $file->url;
              break;
            }
          }
          else if (isset($file->thumbnails))
          {
            if (isset($file->thumbnails[0]))
            {
              $img = $file->thumbnails[0]->url;
              break;
            }
          }
        }
      }

      unset($media);
    }

    unset($content);

    if ($img)
    {
      if ($img[0] == '/')
      {
        $img = $source['host'] . $img;
      }

      $data['lead_image'] = array(
        'type' => 'image',
        'url_original' => $img,
        'url_archived_small' => $img
      );
    }

    $data['fetched_at'] = $img ? time() : 0;

    return $data;
  }
}
