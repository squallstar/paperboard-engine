<?php
/**
 * Source management controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Source_management_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_sources', 'sources');
  }

  public function index()
  {
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->json(200, $this->sources->get_user_categories());
  }

  public function collection_nodes($collection_id)
  {
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->load->model('model_collections', 'collections');

    $collection = $this->collections->find($collection_id, array('sources' => 1));

    if ($collection)
    {
      $this->json(200, $this->sources->tree(array_values($collection['sources'])));
    }
    else
    {
      $this->json(404);
    }
  }

  public function add_category()
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $this->set_body_request();

    if (!isset($this->request['feed_category']['text']))
    {
      return $this->json(400, array('errors' => ['Category name not set']));
    }

    $res = $this->sources->add_feed_category($this->request['feed_category']['text']);

    if ($res)
    {
      $this->json(201, $res);
    } else {
      $this->json(400);
    }
  }

  public function node($node_id)
  {
    if (!$this->require_token()) return;

    if ($this->method == 'delete') return $this->delete_node($node_id);
  }

  public function delete_node($node_id)
  {
    $res = $this->sources->delete($node_id);

    if ($res)
    {
      $this->json(200);
    }
    else
    {
      $this->json(404);
    }
  }

  public function add_feed($category_id)
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $this->set_body_request();

    if ($this->request['feed'] && filter_var($this->request['feed']['url'], FILTER_VALIDATE_URL) !== false)
    {
      $feed = $this->sources->add_feed($category_id, $this->request['feed']['title'], $this->request['feed']['url']);

      if ($feed)
      {
        $this->sources->reorder_category_children($category_id);

        $this->json(201, $feed);
      }
      else
      {
        $this->json(422, ['errors' => ['Could not add the feed']]);
      }
    }
    else
    {
      $this->json(422, ['errors' => ['Feed URL not in a valid format']]);
    }
  }

  public function rename_category($category_id)
  {
    if ($this->method != 'post' || !$this->require_token()) return;

    $name = $this->input->get_post('text');

    if (strlen($name) > 0 && $this->sources->rename_category($category_id, $name))
    {
      $this->json(200);
    }
    else
    {
      $this->json(400);
    }
  }

  public function add_twitter_account()
  {
    if (!$this->require_token()) return;

    $this->load->helper('url');
    $this->load->library(['twitter', 'session']);

    $token = $this->users->token();

    if($this->input->get('oauth_verifier'))
    {
      $this->twitter->set_token();

      $access_token = $this->twitter->get_accesstoken();
      $user = $this->twitter->verify_credentials();

      if ($user)
      {
        if ($user->friends_count > Twitter::MAX_ALLOWED_FRIENDS)
        {
          return show_error("Twitter accounts that follow more than " . Twitter::MAX_ALLOWED_FRIENDS . " people are not allowed to be connected.", 422);
        }

        $account_id = newid('t');
        $source_uri = 'twitter_account:' . $account_id;

        // As a requirement, we remove this twitter account from other users if they connected it
        collection('users')->update(
          [],
          [
            '$pull' => [
              'connected_accounts' => [
                'access_token.screen_name' => $user->screen_name
              ]
            ]
          ],
          ['multiple' => true]
        );

        $res = collection('users')->update(
          array('_id' => $this->users->get('_id')),
          array(
            '$set' => array(
              'full_name' => $user->name,
              'nickname' => $user->screen_name,
              'avatar.small'  => $user->profile_image_url_https,
              'avatar.medium' => $user->profile_image_url_https,
              'avatar.high'   => $user->profile_image_url_https
            ),
            '$push' => array(
              'connected_accounts' => array(
                'id' => $source_uri,
                'processed_at' => 0,
                'connected_at' => time(),
                'type' => 'twitter',
                'access_token' => $access_token,
                'following' => array(
                  'count' => $user->friends_count,
                  'updated_at' => 0
                )
              )
            )
          )
        );

        if ($res)
        {
          $res = $this->sources->add_twitter_category($account_id, '@' . $user->screen_name, $user->name);

          // Also adds myself as a source
          $this->sources->add_twitter_person($res['id'], [
            'id' => $user->id,
            'name' => $user->name,
            'screen_name' => $user->screen_name,
            'avatar' => $user->profile_image_url_https
          ]);

          if (!$res)
          {
            return $this->json(422, ['errors' => ['Cannot add the twitter source']]);
          }

          $this->load->model('model_feeds', 'feeds');
          $this->feeds->update_twitter_followers($this->users->get('_id'));

          $cb = $this->session->userdata('callback');
          $this->session->unset_userdata('callback');

          return redirect($cb ? $cb : $this->config->item('client_base_url'));
        }
        else
        {
          return $this->json(422, ['errors' => ['Cannot add the twitter account']]);
        }
      }
    }

    $this->session->set_userdata('callback', $this->input->get('d'));
    redirect($this->twitter->get_loginurl($token));
  }
}