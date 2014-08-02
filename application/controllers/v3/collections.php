<?php
/**
 * Collections controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Collections_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_collections', 'collections');
  }

  public function index()
  {
    if ($this->method == 'options') return;

    if (!$this->require_token()) return;

    if ($this->method == 'post') return $this->create();

    $this->json(200, collection('collections')->find(array(
      'user.id' => $this->users->get('_id')
    )));
  }

  public function create()
  {
    $this->set_body_request();

    $res = $this->collections->save($this->request['collection']);

    if ($res)
    {
      $this->json(201, $res);
    } else {
      $this->json(422, array('errors' => ['Cannot create the collection']));
    }
  }

  public function view($collection_id)
  {
    if (strpos($collection_id, 'p') !== 0) {
      if (!$this->require_token()) return;
    }

    $this->json(200);
  }

  public function view_links($collection_id)
  {
    if (strpos($collection_id, 'p') !== 0) {
      if (!$this->require_token()) return;
    }

    $this->json(200, array());
  }

  public function favourite_collection_links()
  {
    if (!$this->require_token()) return;

    $this->json(200, array());
  }
}