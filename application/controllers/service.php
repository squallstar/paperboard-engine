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
    ini_set("memory_limit", "256M");

    $this->load->model('model_feeds', 'feeds');

    _log("Downloader worker started!");

    while (true)
    {
      if (!$this->feeds->download())
      {
        sleep(4);
      }
    }
  }

  public function update_followers()
  {
    $this->load->model('model_feeds', 'feeds');

    echo $this->feeds->update_twitter_followers(7);
  }
}