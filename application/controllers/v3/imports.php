<?php
/**
 * Imports controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Imports_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_collections', 'collections');
  }

  public function opml()
  {
    if (!$this->require_token() || $this->method != 'post') return;

    if (!isset($_FILES['file']))
    {
      return $this->json(422, ['errors' => ['OPML file not provided']]);
    }

    $this->load->model('model_sources', 'sources');

    $file = file_get_contents($_FILES['file']['tmp_name']);

    $xml = new SimpleXMLElement($file, LIBXML_NOERROR);

    $n_cat = 0;
    $n_feed = 0;

    foreach ($xml->body->outline as $outline)
    {
      $title = (string) $outline['title'];

      $cat = collection('user_categories')->findOne(
        array(
          'user_id' => $this->users->get('_id'),
          'text' => $title
        ),
        array(
          '_id' => true
        )
      );

      if (!$cat)
      {
        $cat = $this->sources->add_feed_category($title);
      }

      if ($cat && $cat['id'])
      {
        foreach ($outline->outline as $rss)
        {
          if ($rss['type'] != 'rss') continue;

          $this->sources->add_feed($cat['id'], (string)$rss['title'], (string)$rss['xmlUrl']);
          $n_feed++;
        }

        $n_cat++;
      }
    }

    $this->json(200, array(
      'collection_count' => $n_cat,
      'feed_count' => $n_feed,
      'job_id' => null,
      'status' => 'complete'
    ));
  }
}