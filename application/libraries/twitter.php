<?php
/**
 * twitter library
 *
 * @package     Paperboard
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2012, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

require_once 'ext/tmhOAuth.php';

Class Twitter
{
	private $t;

	private $_user_id;

	public function __construct()
	{
		$this->t = new tmhOAuth(array(
			'consumer_key'    => $this->config->item('twitter_consumer_key'),
			'consumer_secret' => $this->config->item('twitter_consumer_secret'),
		));
	}

	public function __get($key)
	{
		return get_instance()->$key;
	}

	public function get_loginurl($token)
	{
		$this->load->helper('url');

		$params = array(
			'oauth_callback'	=> current_url() . '?auth_token=' . $token
		);

		$code = $this->t->request('POST', $this->t->url('oauth/request_token', ''), $params);

		if ($code == 200) {
			$oauth = $this->t->extract_params($this->t->response['response']);
			$method = isset($_REQUEST['authenticate']) ? 'authenticate' : 'authorize';

			$authurl = $this->t->url("oauth/{$method}", '') .  "?oauth_token={$oauth['oauth_token']}";

			$this->load->library('session');
			$this->session->set_userdata('oauth_token', $oauth['oauth_token']);
			$this->session->set_userdata('oauth_token_secret', $oauth['oauth_token_secret']);

			return $authurl;
		}

		return FALSE;
	}

	public function set_token()
	{
		$this->load->library('session');
		$this->_token = $this->session->userdata('oauth_token');
		$this->t->config['user_token']  = $this->_token;
		$this->t->config['user_secret'] = $this->session->userdata('oauth_token_secret');
	}

	public function set_local_token($account)
	{
		$this->_user_id = $account['user_id'];

		$this->t->config['user_token']  = $account['oauth_token'];
		$this->t->config['user_secret'] = $account['oauth_token_secret'];
	}

	//return access_token or FALSE
	public function get_accesstoken()
	{
		$code = $this->t->request('POST', $this->t->url('oauth/access_token', ''), array(
			'oauth_verifier' => $this->input->get('oauth_verifier')
		));

		if ($code == 200) {
			$this->session->unset_userdata('oauth_token');
			$this->session->unset_userdata('oauth_token_secret');
			$token = $this->t->extract_params($this->t->response['response']);

			$this->set_accesstoken($token);
			return $token;
		} else {
			$this->error();
		}
		return FALSE;
	}

	public function set_accesstoken($access_token)
	{
		$this->t->config['user_token']  = $access_token['oauth_token'];
		$this->t->config['user_secret'] = $access_token['oauth_token_secret'];
	}

	public function verify_credentials($access_token = FALSE)
	{
		if ($access_token) {
			$this->t->config['user_token']  = $access_token['oauth_token'];
			$this->t->config['user_secret'] = $access_token['oauth_token_secret'];
		}

		$code = $this->t->request('GET', $this->t->url('1.1/account/verify_credentials'));

		if ($code == 200) {
			return json_decode($this->t->response['response']);
		}
		return FALSE;
	}

	public function error()
	{
		log_message('error', 'Twitter: ' . $this->t->response['response']);
	}

	public function get_friends()
	{
		$params = array(
			'user_id' => $this->_user_id,
			'count' => 200,
			'skip_status' => true,
			'include_user_entities' => false,
			'cursor' => -1
		);

		$friends = [];

		while ($params['cursor'] != 0)
		{
			$code = $this->t->request('GET', $this->t->url('1.1/friends/list'), $params);

			if ($code == 200)
			{
				$response = json_decode($this->t->response['response'], false);

				$params['cursor'] = $response->next_cursor ? intval($response->next_cursor) : 0;

				foreach ($response->users as &$user)
				{
					$friends[] = array(
						'id' => $user->id,
						'name' => $user->name,
						'screen_name' => $user->screen_name,
						'avatar' => $user->profile_background_image_url_https
					);
				}

				unset($user);
				unset($response);
			}
			else
			{
				$params['cursor'] = 0;
			}
		}

		return $friends;
	}

	public function get_users($data = array())
	{
		$params = array(
			'count' => 10,
			'q' => $data['query'],
			'include_entities' => false
		);

		return Cacher::fetch('users-search-' . md5($params['q']), function() use ($params)
		{
			$code = $this->t->request('GET', $this->t->url('1.1/users/search'), $params);

			if ($code != 200)
			{
				log_message('error', 'Error while searching for tweets: ' . $code);
				return array();
			}

			$users = array();

			foreach (json_decode($this->t->response['response']) as $u)
			{
				$followers = $u->followers_count;

				if ($followers >= 1000000) $followers = round($followers / 1000000) . 'm';
				else if ($followers >= 1000) $followers = round($followers / 1000) . 'k';

				$users[] = array(
					'kind' => 'twitter',
					'domain' => '@' . $u->screen_name,
					'source_name' => $u->name,
					'thumbnail' => $u->profile_image_url,
					'followers' => $followers
				);
			}

			return json_encode($users);
		});

	}

	public function get_tweets($data = array())
	{

		$query = isset($data['query']) ? $data['query'] : false;

		$cb = function() use($query, $data)
		{
			$params = array();

			if ($query)
			{
				$path = 'search/tweets';
				$params['q'] = $query;
				$params['count'] = 40;
				//$params['result_type'] = 'popular';

				if (substr($params['q'], 0, 1) == '@')
				{
					$path = 'statuses/user_timeline';
					$params['screen_name'] = substr($params['q'], 1);
					unset($params['q']);
				}
			}
			else
			{
				$path = 'statuses/home_timeline';
				$params['count'] = 15;
				$params['exclude_replies'] = true;
			}

			if ($data['max_id'])
			{
				$params['max_id'] = $data['max_id'];
				$params['count'] = 18;
			}

			$code = $this->t->request('GET', $this->t->url('1.1/' . $path), $params);

			if ($code != 200)
			{
				log_message('error', 'Error while searching for tweets: ' . $code);
				return null;
			}

			if ($query && !isset($params['screen_name']))
			{
				$data = json_decode($this->t->response['response'])->statuses;
			}
			else
			{
				$data = json_decode($this->t->response['response']);
			}

			$colors = $this->config->item('colors');
			$tweets = array();

			$notEncodedUrls = array();
			$urls = array();

			foreach ($data as $tweet)
			{
				if (!$tweet->entities) continue;
				if (!$tweet->entities->urls) continue;

				$txt = preg_replace('/https?:\/\/[\w\-\.!~?&+\*\'"(),\/]+/','<i>$0</i>', $tweet->text);
				$txt = preg_replace('/@[A-z0-9_-]+/','<i>$0</i>', $txt);

				$d = array(
					'id'	  => $tweet->id,
					'source' => 0,
					'kind'  => 'tweet',
					'datepublish' => date('Y-m-d H:i:s', strtotime($tweet->created_at)),
					'title' => $txt,
					'image_url' => false,
					'source_title' => $tweet->user->name,
					//'color' => $colors[array_rand($colors)],
					'color' => '#FFF',
					'collection' => 'Twitter',
					'content' => '',
					'url'   => $tweet->entities->urls[0]->expanded_url,
					'author' => $tweet->user->name,
					'domain' => $tweet->entities->urls[0]->display_url,
					'user'  => array(
						'username'   => $tweet->user->screen_name,
						'avatar'     => $tweet->user->profile_image_url,
						'background' => $tweet->user->profile_background_image_url,
						'link_color' => '#' . $tweet->user->profile_link_color
					)
				);

				$notEncodedUrls[]= 'u[]=' . $d['url'];

				$urls[]= 'u[]=' . urlencode($d['url']);

				if (isset($tweet->entities->media))
				{
					foreach ($tweet->entities->media as $media)
					{
						if ($media->type == 'photo')
						{
							$d['image_url'] = $media->media_url;
							break;
						}
					}
				}

				$tweets[] = $d;
			}

			// Let's use collector new articles api to fetch the tweets contents
			if (count($urls))
			{
				$links = implode('&', $urls);
				$req = 'http://collectorwp.com/api/v1/articles/?' . $links . '&key=' . md5('collector' . implode('&', $notEncodedUrls));

				$data = json_decode(file_get_contents($req));

				if ($data->status == 200)
				{
					foreach ($data->articles as $article)
					{
						foreach ($tweets as &$tweet)
						{
							if ($tweet['url'] == $article->request_url)
							{
								$tweet['expanded'] = true;

								if ($article->title && strlen($article->title) > 25) $tweet['title'] = $article->title;

								if ($article->thumbnail)
								{
									$tweet['image_url'] = $article->thumbnail;
									$tweet['color'] = $colors[array_rand($colors)];
								}

								if ($article->summary) $tweet['content'] = $article->summary;

								break;
							}
						}
					}
				}
			}

			return json_encode($tweets);
		};

		$prefix = $this->_token;
		if ($query) $prefix .= $query;
		if ($data['max_id']) $prefix .= $data['max_id'];

		$key = 'tweets-v5-' . date('Y-m-d-H') . md5($prefix) . '-' . round(date('i')/6);

		return Cacher::fetch($key, $cb);
	}
}