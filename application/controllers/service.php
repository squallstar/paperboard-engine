<?php
/**
 * Manage controller
 *
 * @package     Hhvm
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Service_Controller extends CI_Controller
{
  public function __construct()
  {
    parent::__construct();
  }

  public function start_downloader()
  {
    set_time_limit(0);

    $this->load->model('model_feeds', 'feeds');

    while (true) {
      $this->feeds->download();
      sleep(2);
    }
  }
}