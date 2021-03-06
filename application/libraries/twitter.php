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
	const MAX_ALLOWED_FRIENDS = 4000;

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

		$max = 10;

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
						'avatar' => $user->profile_image_url_https
					);
				}

				unset($user);
				unset($response);
			}
			else
			{
				break;
			}

			$max--;
			if ($max == 0) break;
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

	public function get_tweets($limit = 200)
	{
		$code = $this->t->request('GET', $this->t->url('1.1/statuses/home_timeline'), array(
			'count' => $limit,
			'exclude_replies' => true
		));

		if ($code != 200)
		{
			log_message('error', 'Error while searching for tweets: ' . $code);
			return FALSE;
		}

		$data = json_decode($this->t->response['response']);
		$tweets = [];

		$now = time();

		$url_media = false;

		foreach ($data as &$tweet)
		{
			$url = false;

			if (isset($tweet->entities) && $tweet->entities->urls)
			{
				$url = $tweet->entities->urls[0]->expanded_url;
			}
			else
			{
				if (isset($tweet->entities->media))
				{
					foreach ($tweet->entities->media as &$media)
					{
						if ($media->type == 'photo')
						{
							$url = $media->media_url;
							$url_media = true;
							break;
						}
					}

					unset($media);
				}
			}

			if (!$url) continue;

			$id = 'tweet-' . $tweet->id;

			// Check if tweet exists
			if (collection('articles')->count(['id' => $id])) continue;

			$ts = strtotime($tweet->created_at);

			$d = array(
				'id' => $id,
				'type' => 'tweet',
				'fetched' => false,
				'fetched_at' => 0,
				'processed_at' => $now,
				'published_at' => $ts,
				'name' => preg_replace("/https?:\/\/\S+/", '', $tweet->text),
				'description' => "",
				'content' => "",
				'url' => $url,
				'url_host' => parse_url($url)['host'],
				'lead_image' => null,
				'has_image' => false,
				'tags' => [],
				'images_processed' => true,
				'sources' => array(
					[
						'external_id' => $tweet->user->id,
						'full_name' => $tweet->user->name,
						'screen_name' => $tweet->user->screen_name,
						'type' => 'TwitterUser',
						'profile_image_url' => $tweet->user->profile_image_url_https,
						'published_at' => $ts
					]
				)
			);

			if ($url_media == true)
			{
				$d['fetched'] = true;
				$d['fetched_at'] = time();
			}

			if (isset($tweet->entities->media))
			{
				foreach ($tweet->entities->media as &$media)
				{
					if ($media->type == 'photo')
					{
						$d['lead_image'] = array(
			        'type' => 'image',
			        'url_original' => $media->media_url,
			        'url_archived_small' => $media->media_url
			      );
			      $d['images_processed'] = false;
			      $d['has_image'] = true;
						break;
					}
				}

				unset($media);
			}

			if (isset($tweet->entities->hashtags))
			{
				foreach ($tweet->entities->hashtags as &$tag)
				{
					$d['tags'][] = $tag->text;
				}

				unset($tag);
			}

			$tweets[] = $d;
		}

		unset($d);
		unset($id);
		unset($ts);
		unset($url);
		unset($tweet);
		unset($data);

		return $tweets;
	}
}