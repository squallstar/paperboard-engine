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

	private function _worker_running($name)
	{
		exec("ps -aux | grep start_downloader", $worker);
		return count($worker) > 0 && strpos($worker[0], '/php') !== FALSE;
	}

	public function status()
	{
		$this->json(200, array(
			'collections' => array(
				'count' => collection('collections')->count(),
				'public' => collection('collections')->count(array('publicly_visible' => true))
			),
			'feeds' => array(
				'count'         => collection('feeds')->count(),
				'processed'     => collection('feeds')->count(array('processed_at' => array('$gt' => 1))),
				'not_processed' => collection('feeds')->count(array('processed_at' => 0))
			),
			'articles' => array(
				'count' => collection('articles')->count(),
				'fetched' => collection('articles')->count(array('fetched' => true))
			),
			'workers' => array(
				'downloader' => $this->_worker_running('start_downloader') ? 'running' : 'stopped'
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

		$cat = new MongoCollection($this->db, 'user_categories');
		$cat->ensureIndex(array('id' => 1), array('unique' => true));
		$cat->ensureIndex(array('user_id' => 1));
		$cat->ensureIndex(array('children.id' => 1));

		$cat = new MongoCollection($this->db, 'feeds');
		$cat->ensureIndex(array('url' => 1), array('unique' => true));
		$cat->ensureIndex(array('processed_at' => 1));
		$cat->ensureIndex(array('failed_count' => 1));

		$counters = new MongoCollection($this->db, 'counters');
		$counters->insert(array('_id' => 'user_id', 'seq' => 0));
		$counters->insert(array('_id' => 'collection_id', 'seq' => 0));

		$art = new MongoCollection($this->db, 'articles');
		$art->ensureIndex(array('id' => 1), array('unique' => true));
		$art->ensureIndex(array('source' => 1));
		$art->ensureIndex(array('published_at' => -1));
		$art->ensureIndex(array('fetched' => 1));
		$art->ensureIndex(array('name' => 'text', 'description' => 'text'));

		echo 'done';

	}
}