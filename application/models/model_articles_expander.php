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

  private $_rules;
  private $_common;

  public function __construct()
  {
    parent::__construct();

    $data = json_decode(file_get_contents(APPPATH . 'libraries/reader-rules/rules.json'), true);
    $this->_rules = &$data['hosts'];
    $this->_common = &$data['common'];

    _log(count($this->_rules) . ' rules read from file');
  }

  public function start($limit = 30)
  {
    if ($this->_is_working) return FALSE;

    $articles = iterator_to_array(
      collection('articles')->find(
        [
          'fetched_at' => 0
        ],
        [
          'id' => true,
          'url' => 1
        ]
      )->sort(['processed_at' => -1])
       ->limit($limit)
    , false);

    $n = count($articles);

    if ($n)
    {
      $count = $this->expand($articles);

      _log("Expanded " . $count . " articles");
    }

    unset($articles);

    return $n;
  }

  public function expand(&$articles = array(), $update = true)
  {
    libxml_use_internal_errors(true);

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
      curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
      curl_setopt($curl,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36');


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
            'fetched_at' => time()
          ]]
        );
      }

    }

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

    #_log("Parsing article " . $article['id']);

    # Uses too much memory
    #$html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");

    $doc = new DOMDocument;
    $doc->loadHTML($html);

    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $query = '//*/meta[starts-with(@property, \'og:\')]';
    $metas = [];

    foreach ($xpath->query($query) as $meta) {
      $prop = $meta->getAttribute('property');
      if (!isset($metas[$prop]))
      {
        $metas[$prop] = $meta->getAttribute('content');
      }
    }

    unset($prop);

    if (isset($metas['og:title']) && strlen($metas['og:title']) > 3)
    {
      $article['name'] = trim($metas['og:title']);
    }
    else
    {
      $title = $xpath->query('//title');

      if ($title->length > 0)
      {
        $article['name'] = trim($title->item(0)->textContent);
      }

      unset($title);
    }

    if (isset($metas['og:image']))
    {
      $article['lead_image'] = array(
        'type' => 'image',
        'url_original' => $metas['og:image'],
        'url_archived_small' => $metas['og:image']
      );

      $article['images_processed'] = false;
      $article['has_image'] = true;
    }

    if (isset($metas['og:url']) && strlen($metas['og:url']))
    {
      if (strpos('/', $metas['og:url']) === 0)
      {
        $url = parse_url($metas['og:url']);

        if (strpos('//', $metas['og:url']) === 0)
        {
          $metas['og:url'] = $url['scheme'] . ':' . $metas['og:url'];
        }
        else
        {
          $metas['og:url'] = $url['scheme'] . '://' . $url['host'] . $metas['og:url'];
        }

        unset($url);
      }

      $article['url'] = $metas['og:url'];
    }

    $url = $article['url'];
    if (isset($url['host']))
    {
      $article['url_host'] = $url['host'];
    }
    unset($url);

    if (isset($metas['og:description']))
    {
      $article['description'] = trim($metas['og:description']);
    }
    else
    {
      $desc = $xpath->query('//html/head/meta[@name="description" or @name="Description"]');

      if ($desc->length > 0)
      {
        $article['description'] = trim($desc->item(0)->getAttribute('content'));
      }

      unset($desc);
    }

    $this->find_content($xpath, $article);

    unset($metas);
    unset($xpath);
    unset($doc);

    return true;
  }

  private function _cleanup_doc(&$doc)
  {
    foreach (['script', 'style', 'iframe', 'aside'] as $tag)
    {
      while (($r = $doc->getElementsByTagName($tag)) && $r->length)
      {
        $r->item(0)->parentNode->removeChild($r->item(0));
      }
    }
  }

  public function find_content(&$xpath, &$article)
  {
    if (!isset($article['url_host']))
    {
      $article['url_host'] = parse_url($article['url'])['host'];
    }

    $content = null;

    if (isset($this->_rules[$article['url_host']]))
    {
      $rule = & $this->_rules[$article['url_host']];

      if (isset($rule['cleanup']) && $rule['cleanup'] == true)
      {
        $this->_cleanup_doc($xpath->document);
      }

      if (isset($rule['content']))
      {
        $content = $xpath->query($rule['content']);
      }

      unset($rule);
    }
    else
    {
      $content = $xpath->query($this->_common['content']);
    }

    if (!is_null($content) && $content->length > 0)
    {
      _log("Content found for domain " . $article['url_host']);
      $node = $content->item(0);

      $article['content'] = trim($node->ownerDocument->saveHTML($node));
      unset($node);
    }

    unset($content);
  }
}