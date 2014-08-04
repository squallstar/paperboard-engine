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
      $tree = $this->sources->tree(array_values($collection['sources']));

      $this->json(200, array(
        'twitter' => array(),
        'feed' => $tree
      ));
    }
    else
    {
      $this->json(404);
    }
  }

  public function add_category()
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

  public function node($node_id)
  {
    if (!$this->require_token()) return;

    if ($this->method == 'delete') return $this->delete_node($node_id);
  }

  public function delete_node($node_id)
  {
    $res = $this->sources->delete($node_id);

    if ($res)
    {
      $this->json(200);
    }
    else
    {
      $this->json(404);
    }
  }

  public function add_feed($category_id)
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $this->set_body_request();

    if ($this->request['feed'] && filter_var($this->request['feed']['url'], FILTER_VALIDATE_URL) !== false)
    {
      $feed = $this->sources->add_feed($category_id, $this->request['feed']['title'], $this->request['feed']['url']);

      if ($feed)
      {
        $this->json(201, $feed);
      }
      else
      {
        $this->json(422, ['errors' => ['Could not add the feed']]);
      }
    }
    else
    {
      $this->json(422, ['errors' => ['Feed URL not in a valid format']]);
    }
  }

  public function rename_category($category_id)
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $name = $this->input->get_post('text');

    if (strlen($name) > 0 && $this->sources->rename_category($category_id, $name))
    {
      $this->json(200);
    }
    else
    {
      $this->json(400);
    }
  }
}