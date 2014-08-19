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
		if (count($worker) > 0)
		{
			foreach ($worker as $w)
			{
				if (strpos($w, '/php') !== FALSE) return true;
			}
		}

		return false;
	}

	public function status()
	{
		$this->load->helper('url');

		$today = strtotime("00:00:00");

		$avg = collection('collections')->aggregate([
			'$group' => [
				'_id' => 1,
				'avg_links'   => ['$avg' => '$total_links_count'],
				'avg_sources' => ['$avg' => '$total_source_count'],
				'max_links'   => ['$max' => '$total_links_count'],
				'max_sources' => ['$max' => '$total_source_count']
			]
		])['result'][0];

		$this->json(200, array(
			'live_logs' => site_url('manage/logs'),
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
				'expanded' => [
					'fetched' => collection('articles')->count(array('fetched_at' => array('$gt' => 0))),
					'not_fetched' => collection('articles')->count(array('fetched_at' => 0)),
					'fetched_today' => collection('articles')->count(array('fetched_at' => array('$gt' => $today))),
					'with_content' => collection('articles')->count(array('content' => ['$ne' => ''])),
					'without_content' => collection('articles')->count(array('content' => ''))
				],
				'added' => [
					'last_hour' => collection('articles')->count(['processed_at' => ['$gt' => time() - 3600]]),
					'today' => collection('articles')->count(['processed_at' => ['$gt' => $today]]),
					'yesterday' => collection('articles')->count(['processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]]),
					'this_week' => collection('articles')->count(['processed_at' => ['$gt' => strtotime("-1 week", time())]])
				],
				'average_per_feed' => round(collection('articles')->count() / collection('feeds')->count()),
				'images' => array(
					'with_images' => collection('articles')->count(['lead_image.url_original' => ['$exists' => true]]),
					'without_images' => collection('articles')->count(['lead_image' => null]),
					'processed' => [
						'uploaded' => collection('articles')->count(['images_processed' => true, 'lead_image.url_original' => ['$exists' => true]]),
						'not_uploaded' => collection('articles')->count(['images_processed' => false, 'lead_image.url_original' => ['$exists' => true]]),
						'bucket' => $this->config->item('aws_bucket_name'),
						'resolution' => [
							'width' => collection('articles')->findOne(['images_processed' => true, 'lead_image.url_original' => ['$exists' => true]], ['lead_image' => 1])['lead_image']['width'],
							'height' => 'auto'
						],
						'last_uploaded' => collection('articles')->find(['images_processed' => true, 'lead_image.url_original' => ['$exists' => true]], ['lead_image' => 1])->limit(1)->sort(['published_at' => -1])->getNext()['lead_image']['url_archived_small']
					]
				)
			),
			'workers' => array(
				'memory' => array(
					'usage' => round(memory_get_usage() / 1024) . 'Mb'
				),
				'processes' => array(
					'downloader' => $this->_process_is_running('start_downloader') ? 'running' : 'stopped',
					'expander' => $this->_process_is_running('start_expander') ? 'running' : 'stopped',
					'followers' => $this->_process_is_running('start_followers_updater') ? 'running' : 'stopped',
					'tweets' => $this->_process_is_running('start_tweets_downloader') ? 'running' : 'stopped',
					'images' => $this->_process_is_running('start_images_downloader') ? 'running' : 'stopped',
					'runner' => $this->_process_is_running('start_runner') ? 'running' : 'stopped'
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
		$users->ensureIndex(array('connected_accounts.following.updated_at' => 1));

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
		$col->ensureIndex(array('followers.id' => 1));

		$cat = new MongoCollection($this->db, 'user_categories');
		$cat->ensureIndex(array('id' => 1), array('unique' => true));
		$cat->ensureIndex(array('user_id' => 1));
		$cat->ensureIndex(array('text' => 1));
		$cat->ensureIndex(array('children.id' => 1));
		$cat->ensureIndex(array('children.external_key' => 1));
		$cat->ensureIndex(array('source_uri' => 1));

		$feed = new MongoCollection($this->db, 'feeds');
		$feed->ensureIndex(array('type' => 1));
		$feed->ensureIndex(array('url' => 1), array('unique' => true));
		$feed->ensureIndex(array('processed_at' => 1));
		$feed->ensureIndex(array('failed_count' => 1));
		$feed->ensureIndex(array('external_id' => 1));
		$feed->ensureIndex(array('title' => 'text', 'url' => 'text'));

		$counters = new MongoCollection($this->db, 'counters');
		$counters->insert(array('_id' => 'user_id', 'seq' => 0));
		$counters->insert(array('_id' => 'collection_id', 'seq' => 0));

		$art = new MongoCollection($this->db, 'articles');
		$art->ensureIndex(array('id' => 1), array('unique' => true));
		$art->ensureIndex(array('source' => 1));
		$art->ensureIndex(array('published_at' => -1));
		$art->ensureIndex(array('fetched_at' => 1));
		$art->ensureIndex(array('images_processed' => 1));
		$art->ensureIndex(array('name' => 'text', 'description' => 'text'));

		echo 'done';
	}

	public function expand()
	{
		$this->load->model('model_articles_expander', 'expander');

		$articles = [
			[
				'_id' => new MongoId(),
				'url' => $this->input->get('url')
			]
		];

		$this->expander->expand($articles, false);
		var_dump($articles);
	}

	public function logs()
	{
		$this->load->helper('url');

		echo '<meta http-equiv="refresh" content="5;URL=' . current_url() .'" />';
		echo '<pre>';
		echo str_replace(array(date('Y-m-d H:i')), array("<strong style='color:red;background-color:rgba(255,0,0,0.1);'>&rarr; &rarr; &rarr;</strong> " . date('Y-m-d H:i')), shell_exec("tail -n20 /var/log/cronycle/downloader.log /var/log/cronycle/tweets.log /var/log/cronycle/images.log /var/log/cronycle/expander.log /var/log/cronycle/followers.log"));
		echo '</pre>';
	}

	public function geckoboard($action)
	{
		$today = strtotime("00:00:00");

		switch ($action) {
			case 'links':
				return $this->json(200, [
					'item' => [
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => $today]]),
							'label' => 'Added today'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]]),
							'label' => 'Added yesterday'
						]
					]
				]);
			case 'images':
				return $this->json(200, [
					'item' => [
						[
							'value' => collection('articles')->count(['images_processed' => true, 'lead_image.url_original' => ['$exists' => true]]),
							'label' => 'Uploaded to S3',
							'color' => '00ff00'
						],
						[
							'value' => collection('articles')->count(['images_processed' => false, 'lead_image.url_original' => ['$exists' => true]]),
							'label' => 'Not uploaded',
							'color' => 'ff0000'
						]
					]
				]);
		}
	}
}