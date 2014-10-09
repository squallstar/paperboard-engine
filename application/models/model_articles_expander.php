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
  const GOOSE_ENABLED = false;
  const GOOSE_INSTANCES = 6;
  const GOOSE_URL = "http://paperboard-goose-{i}.herokuapp.com/";

  const GOOSE_INTERNAL_PHP = true;

  private $_is_working;

  private $_i_counter;

  private $_rules;
  private $_common;

  private $_goose;

  public function __construct()
  {
    parent::__construct();

    $this->_rules = [];

    $this->_i_counter = 0;

    if (self::GOOSE_INTERNAL_PHP)
    {
      $this->_goose = new GooseClient();
    }

    foreach (collection('parsers')->find([], ['_id' => false, 'host' => true, 'xpath' => true, 'cleanup' => true]) as $rule)
    {
      $this->_rules[$rule['host']] = $rule;
    }

    $this->_common = [
      "content" => "//div[@id=\"article-body\" or @class=\"article-body\" or @itemprop=\"articleBody\"]",
      "image" => "//*/meta[@property=\"og:image\")]"
    ];

    if ($this->input->is_cli_request()) _log(count($this->_rules) . ' rules read from db');
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

  private function _get_goose_url($article_url)
  {
    $this->_i_counter++;
    if ($this->_i_counter > self::GOOSE_INSTANCES) $this->_i_counter = 0;
    return str_replace('{i}', $this->_i_counter, self::GOOSE_URL) . 'api/article?url=' . urlencode($article_url);
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
      if (self::GOOSE_ENABLED)
      {
        $url = $this->_get_goose_url($article['url']);
      }
      else
      {
        $url = $article['url'];
      }

      $curl = $this->_curl_for_url($url);

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
          if (!self::GOOSE_ENABLED)
          {
            $article['url'] = curl_getinfo($ch[$id], CURLINFO_EFFECTIVE_URL);
          }

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

  private function _parse_with_goose(&$article, &$data)
  {
    $article['extractor'] = 'goose';
    $article['name'] = $data->title;
    $article['url'] = $data->url;
    $article['url_host'] = $data->domain;
    $article['description'] = $data->description;
    $article['content'] = $data->body;

    if (isset($data->image->src))
    {
      $article['lead_image'] = [
        'type' => 'image',
        'url_original' => $data->image->src,
        'url_archived_small' => $data->image->src,
        'width' => $data->image->width,
        'height' => $data->image->height
      ];

      $article['images_processed'] = false;
      $article['has_image'] = true;
    }

    if (isset($data->entities))
    {
      $article['entities'] = [];
      usort($data->entities, function($a, $b) {
        return ($a->frequency < $b->frequency) ? 1 : -1;
      });

      foreach ($data->entities as &$entity)
      {
        if (count($article['entities']) < 6)
        {
          $entity->ltext = strtolower($entity->text);
          $article['entities'][] = $entity;
        }
      }

      unset($entity);
    }

    return true;
  }

  function parse_article(&$article, &$html)
  {
    if (!$html)
    {
      _log("Article " . $article['id'] . ' has no html content');
      return false;
    }

    if (self::GOOSE_ENABLED)
    {
      $goose = json_decode($html);

      if (isset($goose->article))
      {
        $goose = json_decode($html)->article;
        $this->_parse_with_goose($article, $goose);
        unset($goose);
        return true;
      }
      else
      {
        unset($goose);

        $curl = $this->_curl_for_url($article['url']);
        $html = curl_exec($curl);
        unset($curl);

        if (!$html)
        {
          return false;
        }
      }
    }
    else if (self::GOOSE_INTERNAL_PHP)
    {
      $article['extractor'] = 'php-goose';

      $data = $this->_goose->extractContent($article['url'], $html);

      $article['name'] = trim($data->getTitle());
      $article['url_host'] = $data->getDomain();

      if ($data->getMetaDescription()) $article['description'] = $data->getMetaDescription();

      if ($data->getTopImage())
      {
        $article['lead_image'] = array(
          'type' => 'image',
          'url_original' => $data->getTopImage(),
          'url_archived_small' => $data->getTopImage()
        );

        $article['images_processed'] = false;
        $article['has_image'] = true;
      }

      if ($data->getHtmlArticle())
      {
        $article['content'] = $data->getHtmlArticle();
      }

      $article['entities'] = [];
      $tags = $data->getPopularWords(6);

      if (count($tags))
      {
        foreach ($tags as $word => &$frequency)
        {
          $article['entities'][] = [
            'text' => $word,
            'frequency' => $frequency,
            'ltext' => strtolower($word)
          ];
        }
      }

      return true;
    }

    #_log("Parsing article " . $article['id']);

    # Uses too much memory
    #$html = mb_convert_encoding($html, 'HTML-ENTITIES', "UTF-8");

    // $config = array(
    //   'clean' => 'yes',
    //   'hide-comments' => 'yes',
    //   'output-html' => 'yes',
    // );
    // $tidy = tidy_parse_string($html, $config, 'utf8');
    // $tidy->cleanRepair();

    // $html = $tidy->value;

    $doc = new DOMDocument;
    $doc->loadHTML($html);
    #$dom->strictErrorChecking = false;

    libxml_clear_errors();

    $xpath = new DOMXPath($doc);

    $article['extractor'] = 'dom';

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

    $desc = $xpath->query('//html/head/meta[@name="description" or @name="Description"]');

    if ($desc->length > 0)
    {
      $article['description'] = trim($desc->item(0)->getAttribute('content'));
    }
    else if (isset($metas['og:description']))
    {
      $article['description'] = trim($metas['og:description']);
    }

    unset($desc);

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
      try {
        $url = parse_url($article['url']);
        if ($url && isset($url['host']))
        {
          $article['url_host'] = $url['host'];
        }
      }
      catch (Exception $e) {
        return;
      }
    }

    $content = null;

    if (isset($this->_rules[$article['url_host']]))
    {
      $rule = & $this->_rules[$article['url_host']];

      if (isset($rule['cleanup']) && $rule['cleanup'] == true)
      {
        $this->_cleanup_doc($xpath->document);
      }

      if (isset($rule['xpath']))
      {
        $content = $xpath->query($rule['xpath']);
      }

      unset($rule);
    }
    else
    {
      $content = $xpath->query($this->_common['content']);
    }

    if (isset($content->length) && $content->length > 0)
    {
      _log("Content found for domain " . $article['url_host']);
      $node = $content->item(0);

      $article['content'] = trim($node->ownerDocument->saveHTML($node));
      unset($node);
    }

    unset($content);
  }
}