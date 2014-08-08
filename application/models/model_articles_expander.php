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

class Model_articles_expander extends CI_Model
{
  private $_is_working;

  public function start($limit = 30)
  {
    if ($this->_is_working) return FALSE;

    libxml_use_internal_errors(true);

    $articles = iterator_to_array(
      collection('articles')->find(
        [
          'fetched_at' => 0
        ],
        [
          '_id' => true,
          'url' => 1
        ]
      )->sort(['processed_at' => -1])
       ->limit($limit)
    , false);

    $n = count($articles);

    if ($n)
    {
      $count = $this->_expand($articles);

      _log("Expanded " . $count . " articles");
    }
    else
    {
      _log("Nothing to expand");
    }

    unset($articles);

    return $n;
  }

  private function _expand(&$articles = array())
  {
    $this->_is_working = true;

    _log("Started to expand " . count($articles) . " articles");

    // multi curl
    $mh = curl_multi_init();
    $ch = array();

    foreach ($articles as $article)
    {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_URL, $article['url']);
      curl_setopt($curl, CURLOPT_HEADER, 0);
      curl_setopt($curl, CURLOPT_TIMEOUT, 7);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

      $ch[$article['_id']->{'$id'}] = $curl;
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
      $id = $article['_id']->{'$id'};

      $article['fetched_at'] = time();

      if (isset($ch[$id]))
      {
        $curlError = curl_error($ch[$id]);

        if($curlError == "")
        {
          if ($this->parse_article($article, curl_multi_getcontent($ch[$id]))) $count++;
        }
      }

      $id = $article['_id'];
      unset($article['_id']);

      collection('articles')->update(
        ['_id' => $id],
        ['$set' => $article]
      );
    }

    unset($ch);
    unset($article);

    $this->_is_working = false;

    return $count;
  }

  function parse_article(&$article, &$html)
  {
    _log("Parsing article " . $article['_id']->{'$id'});

    $doc = new DOMDocument;
    $doc->loadHTML($html);

    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $query = '//*/meta[starts-with(@property, \'og:\')]';
    $metas = [];

    foreach ($xpath->query($query) as $meta) {
        $property = $meta->getAttribute('property');
        $content = $meta->getAttribute('content');
        $metas[$property] = $content;
    }

    if (isset($metas['og:title']) && strlen($metas['og:title']) > 3)
    {
      $article['name'] = trim($metas['og:title']);
    }
    else
    {
      $article['name'] = trim($xpath->query('//title')->item(0)->textContent);
    }

    if (isset($metas['og:image']))
    {
      $article['lead_image'] = array(
        'type' => 'image',
        'url_original' => $metas['og:image'],
        'url_archived_small' => $metas['og:image']
      );
    }

    if (isset($metas['og:url']))
    {
      $article['url'] = $metas['og:url'];
    }

    if (isset($metas['og:description']))
    {
      $article['description'] = trim($metas['og:description']);
    }
    else
    {
      $desc = $xpath->query('//meta[@name="description"]');

      if ($desc->length > 0)
      {
        $article['description'] = trim($desc->item(0)->textContent);
      }

      unset($desc);
    }

    unset($metas);
    unset($xpath);
    unset($doc);

    return true;
  }
}