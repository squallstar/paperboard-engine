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

  public function start_followers_updater()
  {
    set_time_limit(0);
    ini_set("memory_limit", "256M");

    $this->load->model('model_feeds', 'feeds');

    _log("Followers updater worker started!");

    while (true)
    {
      $count = $this->feeds->update_twitter_followers();

      if ($count === FALSE)
      {
        sleep(300);
      } else {
        _log('Updated ' . $count . " followers.\r\n");
        sleep(200);
      }
    }
  }

  public function start_tweets_downloader()
  {
    set_time_limit(0);
    ini_set("memory_limit", "256M");

    $this->load->model('model_feeds', 'feeds');
    $this->load->library('twitter');

    _log("Tweets downloader worker started!");

    while (true)
    {
      $added = $this->feeds->download_tweets();
      sleep(8);
    }
  }

  public function start_expander()
  {
    set_time_limit(0);
    ini_set("memory_limit", "256M");

    $this->load->model('model_articles_expander', 'expander');

    while (true)
    {
      if ($this->expander->start(30) != 30)
      {
        sleep(10);
      }
    }
  }

  public function start_images_downloader()
  {
    set_time_limit(0);
    ini_set("memory_limit", "256M");

    $this->load->model('model_images_processor', 'images');

    while (true)
    {
      if ($this->images->process(30) != 30)
      {
        sleep(10);
      }
    }
  }
}