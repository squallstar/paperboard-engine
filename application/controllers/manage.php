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

class Manage_Controller extends Cronycle_Controller
{
	public function __construct()
	{
		parent::__construct();

		if (ENVIRONMENT == 'production') show_404();

		$this->db = collection();
	}

	public function index()
	{
		$this->status();
	}

	private function _process_is_running($name)
	{
		exec("ps -aux | grep $name", $worker);
		return count($worker) > 0 && strpos($worker[0], '/php') !== FALSE;
	}

	public function status()
	{
		$today = strtotime("00:00:00");

		$avg = collection('collections')->aggregate([
			'$group' => [
				'_id' => '$type',
				'avg_links'   => ['$avg' => '$total_links_count'],
				'avg_sources' => ['$avg' => '$total_source_count'],
				'max_links'   => ['$max' => '$total_links_count'],
				'max_sources' => ['$max' => '$total_source_count']
			]
		])['result'][0];

		$this->json(200, array(
			'users' => array(
				'count' => collection('users')->count(),
				'connected_accounts' => array(
					'twitter' => collection('users')->count(array('connected_accounts.type' => 'twitter'))
				)
			),
			'collections' => array(
				'count' => collection('collections')->count(),
				'public' => collection('collections')->count(array('publicly_visible' => true)),
				'average' => [
					'links'   => round($avg['avg_links']),
					'sources' => round($avg['avg_sources'])
				],
				'max' => [
					'links' => round($avg['max_links']),
					'sources' => round($avg['max_sources'])
				]
			),
			'feeds' => array(
				'count'         => collection('feeds')->count(array('type' => 'feed')),
				'processed'     => collection('feeds')->count(array('type' => 'feed', 'processed_at' => ['$gt' => 1])),
				'not_processed' => collection('feeds')->count(array('type' => 'feed', 'processed_at' => 0)),
				'outdated'      => collection('feeds')->count(array('type' => 'feed', 'processed_at' => ['$lt' => time() - 3600])),
				'broken'        => collection('feeds')->count(array('type' => 'feed', 'failed_count' => ['$gt' => 4]))
			),
			'tweets' => array(
				'sources' => collection('feeds')->count(array('type' => 'twitter_user')),
				'count' => collection('articles')->count(array('type' => 'tweet')),
				'processed' => collection('articles')->count(array('type' => 'tweet', 'fetched_at' => array('$gt' => 0))),
				'not_processed' => collection('articles')->count(array('type' => 'tweet', 'fetched_at' => 0)),
				'added' => array(
					'today' => collection('articles')->count(array('type' => 'tweet', 'processed_at' => ['$gt' => $today])),
					'yesterday' => collection('articles')->count(array('type' => 'tweet', 'processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]))
				)
			),
			'articles' => array(
				'count' => collection('articles')->count(),
				'fetched' => collection('articles')->count(array('fetched_at' => array('$gt' => 0))),
				'not_fetched' => collection('articles')->count(array('fetched_at' => 0)),
				'added' => [
					'today' => collection('articles')->count(['processed_at' => ['$gt' => $today]]),
					'yesterday' => collection('articles')->count(['processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]]),
				],
				'average_per_feed' => round(collection('articles')->count() / collection('feeds')->count())
			),
			'workers' => array(
				'memory' => array(
					'usage' => round(memory_get_usage() / 1024) . 'Mb'
				),
				'processes' => array(
					'downloader' => $this->_process_is_running('start_downloader') ? 'running' : 'stopped',
					'expander' => $this->_process_is_running('start_expander') ? 'running' : 'stopped',
					'followers' => $this->_process_is_running('start_followers_updater') ? 'running' : 'stopped',
					'tweets' => $this->_process_is_running('start_tweets_downloader') ? 'running' : 'stopped'
				)
			)
		));
	}

	public function recreate()
	{
		if (!$this->input->is_cli_request()) die('Please run this from CLI');

		ini_set("memory_limit","128M");

		$this->db->drop();

		$users = new MongoCollection($this->db, 'users');
		$users->ensureIndex(array('email' => 1), array('unique' => true));
		$users->ensureIndex(array('password' => 1));
		$users->ensureIndex(array('auth_token' => 1), array('unique' => true));
		$users->ensureIndex(array('connected_accounts.id' => 1));

		$cats = new MongoCollection($this->db, 'categories');
		$cats->ensureIndex(array('id' => 1), array('unique' => true));
		$cats->ensureIndex(array('slug' => 1), array('unique' => true));

		collection('categories')->save(array('id' => newid('c'), 'name' => 'Featured', 'slug' => 'top-picks', 'collection_count' => 0));
		collection('categories')->save(array('id' => newid('c'), 'name' => 'Tech', 'slug' => 'tech', 'collection_count' => 0));
		collection('categories')->save(array('id' => newid('c'), 'name' => 'Business', 'slug' => 'business', 'collection_count' => 0));
		collection('categories')->save(array('id' => newid('c'), 'name' => 'Sport', 'slug' => 'sport', 'collection_count' => 0));

		$col = new MongoCollection($this->db, 'collections');
		$col->ensureIndex(array('id' => 1), array('unique' => true));
		$col->ensureIndex(array('private_id' => 1), array('unique' => true));
		$col->ensureIndex(array('user.id' => 1));
		$col->ensureIndex(array('position' => 1));
		$col->ensureIndex(array('publicly_visible' => 1));
		$col->ensureIndex(array('category.slug' => 1));

		$cat = new MongoCollection($this->db, 'user_categories');
		$cat->ensureIndex(array('id' => 1), array('unique' => true));
		$cat->ensureIndex(array('user_id' => 1));
		$cat->ensureIndex(array('text' => 1));
		$cat->ensureIndex(array('children.id' => 1));
		$cat->ensureIndex(array('source_uri' => 1));

		$feed = new MongoCollection($this->db, 'feeds');
		$feed->ensureIndex(array('type' => 1));
		$feed->ensureIndex(array('url' => 1), array('unique' => true));
		$feed->ensureIndex(array('processed_at' => 1));
		$feed->ensureIndex(array('failed_count' => 1));
		$feed->ensureIndex(array('title' => 'text', 'url' => 'text'));

		$counters = new MongoCollection($this->db, 'counters');
		$counters->insert(array('_id' => 'user_id', 'seq' => 0));
		$counters->insert(array('_id' => 'collection_id', 'seq' => 0));

		$art = new MongoCollection($this->db, 'articles');
		$art->ensureIndex(array('id' => 1), array('unique' => true));
		$art->ensureIndex(array('source' => 1));
		$art->ensureIndex(array('published_at' => -1));
		$art->ensureIndex(array('fetched_at' => 1));
		$art->ensureIndex(array('name' => 'text', 'description' => 'text'));

		echo 'done';
	}

	public function test_curl()
	{
		$curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'http://ift.tt/1mBGRNJ');
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_TIMEOUT, 7);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 1);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);

    $output = curl_exec($curl);

    curl_close($curl);

    debug($output);die;
	}
}