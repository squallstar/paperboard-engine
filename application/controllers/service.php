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
  const SLEEP_TIME_JOBS = 30;
  const SLEEP_TIME_RUNNER = 180;
  const SLEEP_TIME_DOWNLOADER = 10;
  const SLEEP_TIME_FOLLOWERS = 400;
  const SLEEP_TIME_TWEETS = 8;
  const SLEEP_TIME_EXPANDER = 5;
  const SLEEP_TIME_IMAGES = 10;

  const IMAGES_TO_PROCESS_PER_CYCLE = 25;

  const CLEANUP_FEEDS_LIMIT = 25;
  const CLEANUP_ARTICLES_LIMIT = 300;

  public function __construct()
  {
    parent::__construct();

    set_time_limit(0);
    ini_set("memory_limit", "256M");

    if (!$this->input->is_cli_request())
    {
      die('Services can only be launched by CLI');
    }
  }

  public function start_jobs()
  {
    $this->load->model('threads/jobs_processor', 'jobs');

    _log("Jobs processor worker started!");

    while (true)
    {
      $this->jobs->process();

      sleep(self::SLEEP_TIME_JOBS);
    }
  }

  public function start_runner()
  {
    $this->load->model('model_runner', 'runner');
    $this->load->model('model_feeds', 'feeds');

    _log("Runner worker started!");

    while (true)
    {
      _log("Stats updated for " . $this->runner->update_collections_metadata() . " collections");

      $res = $this->feeds->cleanup_unused_articles(self::CLEANUP_FEEDS_LIMIT, self::CLEANUP_ARTICLES_LIMIT);
      _log("Removed " . $res['feeds'] . " unused sources with " . $res['articles'] . " articles.");

      $removed = $this->feeds->cleanup_old_articles();
      _log("Removed " . $removed . " old articles.");

      # Keep-alive heroku
      file_get_contents("https://cronycle-web-hhvm.herokuapp.com/");

      sleep(self::SLEEP_TIME_RUNNER);
    }
  }

  public function start_downloader()
  {
    $this->load->model('model_feeds', 'feeds');

    _log("Feeds downloader worker started!");

    while (true)
    {
      if (!$this->feeds->download())
      {
        sleep(self::SLEEP_TIME_DOWNLOADER);
      }
    }
  }

  public function start_followers_updater()
  {
    $this->load->model('model_feeds', 'feeds');

    _log("Followers updater worker started!");

    while (true)
    {
      $count = $this->feeds->update_twitter_followers();

      if ($count !== FALSE)
      {
        _log('Updated ' . $count . " followers.");
      }

      sleep(self::SLEEP_TIME_FOLLOWERS);
    }
  }

  public function start_tweets_downloader()
  {
    $this->load->model('model_feeds', 'feeds');
    $this->load->library('twitter');

    _log("Tweets downloader worker started!");

    while (true)
    {
      $this->feeds->download_tweets();
      $this->feeds->download_instagram_pics();
      sleep(self::SLEEP_TIME_TWEETS);
    }
  }

  public function start_expander()
  {
    $this->load->model('model_articles_expander', 'expander');

    $n = 30;

    while (true)
    {
      if ($this->expander->start($n) != $n)
      {
        sleep(self::SLEEP_TIME_EXPANDER);
      }
    }
  }

  public function start_images_downloader()
  {
    $this->load->model('model_images_processor', 'images');

    $n = self::IMAGES_TO_PROCESS_PER_CYCLE;

    while (true)
    {
      if ($this->images->process($n) != $n)
      {
        sleep(self::SLEEP_TIME_IMAGES);
      }
    }
  }
}