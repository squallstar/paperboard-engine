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

use Goose\Client as GooseClient;

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
				'added' => array(
					'today' => collection('articles')->count(array('type' => 'tweet', 'processed_at' => ['$gt' => $today])),
					'yesterday' => collection('articles')->count(array('type' => 'tweet', 'processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]))
				)
			),
			'instagram' => array(
				'sources' => collection('feeds')->count(array('type' => 'instagram_user')),
				'count' => collection('articles')->count(array('type' => 'instagram')),
				'added' => array(
					'today' => collection('articles')->count(array('type' => 'instagram', 'processed_at' => ['$gt' => $today])),
					'yesterday' => collection('articles')->count(array('type' => 'instagram', 'processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]))
				)
			),
			'articles' => array(
				'count' => collection('articles')->count(),
				'expanded' => [
					'fetched' => collection('articles')->count(array('fetched' => true)),
					'not_fetched' => collection('articles')->count(array('fetched' => false)),
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
					'with_images' => collection('articles')->count(['has_image' => true]),
					'without_images' => collection('articles')->count(['has_image' => false]),
					'processed' => [
						'uploaded' => collection('articles')->count(['images_processed' => true, 'has_image' => true]),
						'not_uploaded' => collection('articles')->count(['images_processed' => false, 'has_image' => true]),
						'bucket' => $this->config->item('aws_bucket_name'),
						'resolution' => [
							'width' => collection('articles')->findOne(['images_processed' => true, 'has_image' => true], ['lead_image' => 1])['lead_image']['width'],
							'height' => 'auto'
						],
						'last_uploaded' => collection('articles')->find(['images_processed' => true, 'has_image' => true], ['lead_image' => 1])->limit(1)->sort(['published_at' => -1])->getNext()['lead_image']['url_archived_small']
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
		set_time_limit(0);

		$this->config->load('schema');
		$db_schema = $this->config->item('db_schema');

		if ($db_schema['drop_database'])
		{
			$this->db->drop();
		}

		$created = 0;

		foreach ($db_schema['collections'] as $collection_name => $schema)
		{
			$collection = new MongoCollection($this->db, $collection_name);

			if (isset($schema['indexes']))
			{
				foreach ($schema['indexes'] as $index)
				{
					if (isset($index[1]))
					{
						$collection->ensureIndex($index[0], $index[1]);
					}
					else
					{
						$collection->ensureIndex($index[0]);
					}
				}
			}

			if (($db_schema['drop_database'] || $db_schema['insert_data']) && isset($schema['data']))
			{
				foreach ($schema['data'] as $record)
				{
					try
					{
						$collection->insert($record);
					}
					catch (Exception $e) {

					}
				}
			}

			$created++;
		}

		$this->json(200, [
			'created' => $created,
			'schema' => $this->config->item('db_schema')
		]);
	}

	public function expand()
	{
		$url = $this->input->get('url');

		$this->load->model('model_articles_expander', 'expander');

		if ($url)
		{
			$articles = [
				[
					'id' => '1',
					'url' => $this->input->get('url')
				]
			];

			$this->expander->expand($articles, false);
		}

		echo '<hr />';
		echo '<style>*{font-family:"Proxima Nova", sans-serif}</style>';
		echo '<form method="GET"><input placeholder="http://example.org" style="width: 80%;padding:10px;font-size:14px;" name="url" value="' . $url . '"></form>';
		echo '<hr />';

		if (isset($articles))
		{
			echo '<h1>' . $articles[0]['name'] . '</h1>';
			echo '<hr />';
			echo '<img style="max-width:500px" src="' . $articles[0]['lead_image']['url_original'] . '" />';
			echo '<hr />';
			echo '<h4>Description</h4>';
			echo '<p>' . $articles[0]['description'] . '</p>';
			echo '<hr />';

			if (isset($articles[0]['entities']))
			{
				echo '<p>' . json_encode($articles[0]['entities']) . '</p>';
				echo '<hr />';
			}

			if (isset($articles[0]['content']))
			{
				echo '<h4>Content</h4>';
				echo '<p>' . $articles[0]['content'] . '</p>';
			}
		}
	}

	public function logs()
	{
		$this->load->helper('url');

		echo '<meta http-equiv="refresh" content="5;URL=' . current_url() .'" />';
		echo '<pre>';
		echo str_replace(array(date('Y-m-d H:i')), array("<strong style='color:red;background-color:rgba(255,0,0,0.1);'>&rarr; &rarr; &rarr;</strong> " . date('Y-m-d H:i')), shell_exec("tail -n20 /var/log/cronycle/downloader.log /var/log/cronycle/tweets.log /var/log/cronycle/images.log /var/log/cronycle/expander.log /var/log/cronycle/followers.log"));
		echo '</pre>';
	}

	public function geckoboard($action, $sub_action = '')
	{
		set_time_limit(0);
    ini_set("memory_limit", "256M");

		$today = strtotime("00:00:00");

		switch ($action) {
			case 'money':
				return $this->json(200, [
					'item' => [
						'text' => 'Spent today',
						'value' => "2." . rand(0,99),
						'prefix' => '£'
					]
				]);

			case 'links':
				return $this->json(200, [
					'item' => [
						[
							'value' => collection('articles')->count(),
							'label' => 'Total in system'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => time() - 86400]]),
							'label' => 'Added in the last 24 hours'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => $today]]),
							'label' => 'Added today'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$lt' => $today, '$gt' => strtotime("-1 day", $today)]]),
							'label' => 'Added yesterday'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => time() - 7200]]),
							'label' => 'Added in the last two hours'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => time() - 3600]]),
							'label' => 'Added in the last hour'
						],
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => time() - 300]]),
							'label' => 'Added in the last 5 minutes'
						]
					]
				]);

			case 'withcontent':
				$ex = collection('articles')->find(array('content' => ['$ne' => '']))->timeout(-1)->count();
				$count = collection('articles')->count();
				return $this->json(200, [
					'item' => $ex,
					'min' => [
						'value' => 0,
						'text' => ''
					],
					'max' => [
						'value' => $count,
						'text' => round(100*$ex/$count) . "% of total"
					]
				]);

			case 'fetched':
				return $this->json(200, [
					'item' => [
						[
							'value' => collection('articles')->count(array('fetched_at' => array('$gt' => time() - 300))),
							'label' => 'Fetched in the last 5 minutes'
						],
						[
							'value' => collection('articles')->count(array('fetched_at' => array('$gt' => time() - 60))),
							'label' => 'Fetched in the last minute'
						],
						[
							'value' => collection('articles')->count(array('fetched_at' => 0)),
							'label' => 'Queued (not fetched yet)'
						],
					]
				]);

			case 'images':
				$not = collection('articles')->count(['images_processed' => false, 'has_image' => true]);
				return $this->json(200, [
					'item' => [
						[
							'value' => collection('articles')->count(['processed_at' => ['$gt' => $today], 'images_processed' => true, 'has_image' => true]),
							'label' => 'Uploaded today',
							'color' => '8DC345'
						],
						[
							'value' => $not,
							'label' => 'Queued (' . $not . ')',
							'color' => 'ff0000'
						]
					]
				]);

			case 'tweets':
				$count = collection('articles')->count();
				$tweets = collection('articles')->count(array('type' => 'tweet'));
				$insta = collection('articles')->count(array('type' => 'instagram'));

				return $this->json(200, [
					'item' => [
						[
							'value' => $tweets,
							'label' => 'Tweets (' . round(100*$tweets/$count) . '%)',
							'color' => '4C9FDB'
						],
						[
							'value' => $insta,
							'label' => 'Instagram (' . round(100*$insta/$count) . '%)',
							'color' => '0C0450'
						],
						[
							'value' => $count - $tweets,
							'label' => 'Feeds articles (' . round(100*($count - $tweets - $insta)/$count) . '%)',
							'color' => 'FD9226'
						]
					]
				]);

			case 'timelines':
				$accs = collection('users')->find(['connected_accounts.type' => 'twitter']);

				$count = 0;
				$up_to_date = 0;
				$semi_up_to_date = 0;
				$ts = time() - 65;
				$ts_semi = time() - 120;

				foreach ($accs as $user) {
					foreach ($user['connected_accounts'] as $account)
					{
						if ($account['processed_at'] >= $ts) $up_to_date++;
						else if ($account['processed_at'] >= $ts_semi) $semi_up_to_date++;

						$count++;
					}
				}

				return $this->json(200, [
					'orientation' => 'horizontal',
					'item' => [
						'label' => 'Twitter timelines',
						'sublabel' => round($up_to_date*100/$count) . "% updated less than 1min ago",
						'axis' => [
							'point' => [
								"0", "" . round($count/2), "" . $count
							]
						],
						'range' => [
							[
								'color' => 'red',
								'start' => 0,
								'end' => $count-$up_to_date-$semi_up_to_date
							],
							[
								'color' => 'amber',
								'start' => $count-$up_to_date-$semi_up_to_date,
								'end' => $count-$up_to_date
							],
							[
								'color' => 'green',
								'start' => $count-$up_to_date,
								'end' => $count
							]
						],
						'measure' => [
							'current' => [
								'start' => 0,
								'end' => 0
							],
							'projected' => [
								'start' => 0,
								'end' => $count-$up_to_date
							]
						]
					]
				]);

			case 'instagramtimelines':
				$accs = collection('users')->find(['connected_accounts.type' => 'instagram']);

				$count = 0;
				$up_to_date = 0;
				$semi_up_to_date = 0;
				$ts = time() - 120;
				$ts_semi = time() - 180;

				foreach ($accs as $user) {
					foreach ($user['connected_accounts'] as $account)
					{
						if ($account['processed_at'] >= $ts) $up_to_date++;
						else if ($account['processed_at'] >= $ts_semi) $semi_up_to_date++;

						$count++;
					}
				}

				return $this->json(200, [
					'orientation' => 'horizontal',
					'item' => [
						'label' => 'Instagram timelines',
						'sublabel' => round($up_to_date*100/$count) . "% updated less than 2mins ago",
						'axis' => [
							'point' => [
								"0", "" . round($count/2), "" . $count
							]
						],
						'range' => [
							[
								'color' => 'red',
								'start' => 0,
								'end' => $count-$up_to_date-$semi_up_to_date
							],
							[
								'color' => 'amber',
								'start' => $count-$up_to_date-$semi_up_to_date,
								'end' => $count-$up_to_date
							],
							[
								'color' => 'green',
								'start' => $count-$up_to_date,
								'end' => $count
							]
						],
						'measure' => [
							'current' => [
								'start' => 0,
								'end' => 0
							],
							'projected' => [
								'start' => 0,
								'end' => $count-$up_to_date
							]
						]
					]
				]);

			case 'workers':
				return $this->json(200, [
					'status' => ($this->_process_is_running('start_' . $sub_action) ? 'Up' : 'Down'),
					'responseTime' => rand(2, 20)
				]);

		// query is too slow
		// 	case 'domains':
		// 		$pipeline = array(
		// 		    array(
		// 	        '$group' => array(
		// 	            '_id' => '$url_host',
		// 	            'value' => ['$sum' => 1]
		// 	        )
		// 		    ),
		// 		    array(
		// 		    	'$sort' => ['value' => -1]
		// 		    ),
		// 		    array(
		// 		    	'$limit' => 9
		// 		    )
		// 		);
		// 		$out = collection('articles')->aggregate($pipeline);
		// 		$items = [
		// 			[
		// 				'value' => collection('articles')->count(),
		// 				'label' => 'All articles'
		// 			]
		// 		];

		// 		foreach ($out['result'] as $domain)
		// 		{
		// 			$items[] = [
		// 				'value' => $domain['value'],
		// 				'label' => $domain['_id']
		// 			];
		// 		}

		// 		return $this->json(200, [
		// 			'item' => $items
		// 		]);
		}
	}

	public function fill()
	{
		set_time_limit(0);
    ini_set("memory_limit", "256M");

		$article = collection('articles')->findOne(['id' => 'tweet-504916664283328512']);
		$article['source'] = collection('feeds')->findOne(['title' => 'DEMO.DEMO.DEMO'])['_id']->{'$id'};
		unset($article['_id']);

		for ($i=0; $i < 500000; $i++) {
			$article['id'] = 'demo-' . newid() . rand(0,99999) . rand(0,999999);
			$article['_id'] = $article['id'];
			$article['processed_at'] = time();
			collection('articles')->insert($article);
		}
	}

	public function migrate()
	{
		// set_time_limit(0);
  //   ini_set("memory_limit", "256M");

  //   collection('articles')->update(
  //   	['lead_image.url_original' => ['$exists' => true]],
  //   	['has_image' => true],
  //   	['justOne' => false]
  //   );
	}

	public function search()
	{
		/*
		"restaurants" london
		"restaurants" paris
		*/

		$str = '{
   "match": "and",
   "items": [
      "restaurants",
      {
         "match": "or",
         "items": [
            "london",
            "paris"
         ]
      },
      {
         "match": "or",
         "items": [
            "hipsters",
            "indie"
         ]
      },
      {
         "match": "or",
         "items": [
            "soho",
            "brick lane"
         ]
      }
   ]
}';

		$data = json_decode($str);

		$query = '';

		$this->flatQuery($data, $query);

		$res = collection('articles')->find(
			[
				'$text' => ['$search' => $query]
			]
		)->limit(10);

		var_dump(iterator_to_array($res));
	}

	public function cleanup()
	{
		set_time_limit(0);
    ini_set("memory_limit", "256M");

		$this->load->model('model_feeds', 'feeds');

		$res = $this->feeds->cleanup_unused_articles(100, 1000);

		$this->json(200, $res);
	}

	public function flatQuery(&$grp, &$phrase)
	{
		foreach ($grp->items as $item)
		{
			if (is_string($item))
			{
				if ($grp->match == 'and') $phrase .= ' "' . $item . '"';
				else $phrase .= ' ' . $item;
			}
			else
			{
				$this->flatQuery($item, $phrase);
			}
		}
	}

	public function queryForGroup(&$grp, &$phrases)
	{
		if ($grp->match == 'and')
		{
			foreach ($grp->items as $item)
			{
				if (is_string($item))
				{
					if (count($phrases))
					{
						foreach ($phrases as &$phrase) {
							$phrase .= ' "' . $item . '"';
						}
					}
					else
					{
						$phrases[] = '"' . $item . '"';
					}
				}
				else
				{
					$this->queryForGroup($item, $phrases);
				}
			}
		}
		else
		{
			$orig_phrases = $phrases;
			$phrases = [];

			foreach ($grp->items as $item)
			{
				if (is_string($item))
				{
					if (count($orig_phrases))
					{
						foreach ($orig_phrases as &$phrase)
						{
							$phrases[] = $phrase . ' ' . $item;
						}
					}
					else
					{
						$phrases[] = $item;
					}
				}
				else
				{
					$this->queryForGroup($item, $phrases);
				}
			}
		}
	}

	public function phpgoose()
	{
		$start = microtime();
    $start = explode(' ', $start);
    $start = $start[1] + $start[0];

		$goose = new GooseClient();

		$url = $this->input->get('url');
		$content = file_get_contents($url);
		$article = $goose->extractContent($url, $content);

		$end = microtime();
    $end = explode(' ', $end);
    $end = $end[1] + $end[0];
    $tot = round(($end - $start), 3);

		$start = microtime();
    $start = explode(' ', $start);
    $start = $start[1] + $start[0];

		$a = [
			['id' => 1, 'url' => $this->input->get('url')]
		];

		$this->load->model('model_articles_expander', 'expander');
		$this->expander->expand($a, false);

		$end = microtime();
    $end = explode(' ', $end);
    $end = $end[1] + $end[0];
    $tot2 = round(($end - $start), 3);

		$a = $a[0];

		$this->json(200, [
			'time' => [
				'php' => $tot,
				'java' => $tot2
			],
			'data' => [
				'title_p' => trim($article->getTitle()),
				'title_j' => $a['name'],
				'description_p' => $article->getMetaDescription(),
				'description_j' => $a['description'],
				'published_at' => $article->getPublishDate(),
				'image_p' => $article->getTopImage(),
				'image_j' => $a['lead_image'],
				'url_p' => $article->getDomain(),
				'url_j' => $a['url_host'],
				'tags_p' => $article->getPopularWords(),
				'tags_j' => $a['entities'],
				'content_p' => $article->getHtmlArticle(),
				'content_j' => $a['content'],
				'og' => $article->getOpenGraphData()
			]
		]);
	}
}