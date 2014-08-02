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
  public function index()
  {
    if (!$this->require_token()) return;

    if ($this->method == 'post') return $this->create();

    $this->json(200, array());
  }

  public function create()
  {

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