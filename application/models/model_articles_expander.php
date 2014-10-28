<?php
/**
 * article downloader model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

use Goose\Client as GooseClient;

class Model_articles_expander extends CI_Model
{
  private $_is_working;
  private $_goose;

  public function __construct()
  {
    parent::__construct();
    $this->_goose = new GooseClient();
  }

  public function start($limit = 30)
  {
    if ($this->_is_working) return FALSE;

    $articles = iterator_to_array(
      collection('articles')->find(
        [
          'fetched' => false
        ],
        [
          'id' => true,
          'url' => 1
        ]
      )->sort(['processed_at' => -1])
       ->hint(['fetched' => 1])
       ->limit($limit)
    , false);

    $n = count($articles);

    if ($n)
    {
      $count = $this->expand($articles);
    }

    unset($articles);

    return $n;
  }

  private function _curl_for_url($url)
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 6);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36');

    return $curl;
  }

  public function expand(&$articles = array(), $update = true)
  {
    libxml_use_internal_errors(true);

    $this->_is_working = true;

    if ($update) _log("Started to expand " . count($articles) . " articles");

    $start = microtime();
    $start = explode(' ', $start);
    $start = $start[1] + $start[0];

    // multi curl
    $mh = curl_multi_init();
    $ch = array();

    foreach ($articles as $article)
    {
      $curl = $this->_curl_for_url($article['url']);

      $ch[$article['id']] = $curl;
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

    $count = 0;

    foreach ($articles as &$article)
    {
      $id = $article['id'];

      $article['fetched'] = true;
      $article['fetched_at'] = time();

      if (isset($ch[$id]))
      {
        $curlError = curl_error($ch[$id]);

        if($curlError == "")
        {
          $article['url'] = curl_getinfo($ch[$id], CURLINFO_EFFECTIVE_URL);

          $this->parse_article($article, curl_multi_getcontent($ch[$id]));
          $count++;
        }
        else
        {
          _log('Cannot get content for article ' . $article['id'] . ' from ' . $article['url']);
        }
      }

      unset($article['id']);


      try {
        collection('articles')->update(
          ['id' => $id],
          ['$set' => $article]
        );
      } catch (Exception $e)
      {
        _log("Error expanding article " . $id);
        collection('articles')->update(
          ['id' => $id],
          ['$set' => [
            'fetched_at' => time(),
            'fetched' => true
          ]]
        );
      }
    }

    $end = microtime();
    $end = explode(' ', $end);
    $end = $end[1] + $end[0];
    $tot = round(($end - $start), 3);

    if ($update) _log("Expanded in " . $tot . "s. (" . round($tot / count($articles), 3) . " per article)");

    unset($ch);
    unset($article);

    $this->_is_working = false;

    return $count;
  }

  function parse_article(&$article, &$html)
  {
    if (!$html)
    {
      _log("Article " . $article['id'] . ' has no html content');
      return false;
    }

    _log("Extracting " . $article['url']);

    $data = $this->_goose->extractContent($article['url'], $html);

    $article['name'] = trim($data->getTitle());
    if (!strlen($article['name'])) unset($article['name']);

    $article['url_host'] = $data->getDomain();

    if ($data->getMetaDescription()) $article['description'] = $data->getMetaDescription();

    $img = $data->getTopImage();

    if ($img)
    {
      if (strpos($img, '//') === 0)
      {
        $img = 'http:' . $img;
      }
      else if (strpos($img, '/') === 0)
      {
        $img = 'http://' . $article['url_host'] . $img;
      }

      $article['lead_image'] = array(
        'type' => 'image',
        'url_original' => $img,
        'url_archived_small' => $img
      );

      $article['images_processed'] = false;
      $article['has_image'] = true;
    }

    if ($data->getHtmlArticle())
    {
      $article['content'] = $data->getHtmlArticle();
    }

    $article['entities'] = [];
    $tags = $data->getPopularWords(20);

    if (count($tags))
    {
      foreach ($tags as $word => $frequency)
      {
        $article['entities'][] = [
          'text' => $word,
          'frequency' => $frequency,
          'ltext' => strtolower($word)
        ];
      }
    }

    $article['extractor'] = 'goose';

    return true;
  }
}