<?php
/**
 * Source management controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Source_management_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_sources', 'sources');
  }

  public function index()
  {
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->json(200, array(
      'twitter' => array(),
      'feed' => $this->sources->get_user_feed_categories()
    ));
  }

  public function collection_nodes($collection_id)
  {
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->load->model('model_collections', 'collections');

    $collection = $this->collections->find($collection_id, array('sources'));

    if ($collection)
    {
      $this->json(200, array(
        'twitter' => array(),
        'feed' => $collection['sources']
      ));
    }
    else
    {
      $this->json(404);
    }
  }

  public function add_feed_category()
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $this->set_body_request();

    if (!isset($this->request['feed_category']['text']))
    {
      return $this->json(400, array('errors' => ['Category name not set']));
    }

    $res = $this->sources->add_feed_category($this->request['feed_category']['text']);

    if ($res)
    {
      $this->json(201, $res);
    } else {
      $this->json(400);
    }
  }

  public function add_feed($category_id)
  {

  }
}