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

    $this->json(200, $this->sources->get_user_categories(true));
  }

  public function collection_nodes($collection_id)
  {
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->load->model('model_collections', 'collections');

    $collection = $this->collections->find($collection_id, array('sources' => 1));

    if ($collection)
    {
      $flat = $this->input->get('flat') ? true: false;

      $this->json(200, $this->sources->tree($collection['sources'], $flat));
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

  public function move_node($nodes_ids)
  {
    if (!$this->require_token()) return;

    if ($this->method != 'post') return $this->json(404);

    $moved = [];
    $nodes = explode(',', $nodes_ids);

    $new_folder = $this->input->get_post('new_parent_id');

    if (!$new_folder)
    {
      return $this->json(422, ['errors' => ['New folder is required']]);
    }

    foreach ($nodes as &$node_id)
    {
      $moved[] = $this->sources->move_node($node_id, $new_folder);
    }

    $this->json(200, $moved);
  }

  public function delete_node($node_id)
  {
    $nodes = explode(',', $node_id);

    $res = [];
    foreach ($nodes as $node_id) {
      $res[] = $this->sources->delete($node_id) ? true : false;
    }

    if ($res)
    {
      $this->json(200, $res);
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

  public function add_instagram_account()
  {
    if (!$this->require_token()) return;

    $this->load->helper('url');
    $this->load->library('instagram', [
      'callback' => current_url() . '?auth_token=' . $this->users->token()
    ]);

    $code = $this->input->get('code');

    if ($code)
    {
      $resp = $this->instagram->getOAuthToken($code);

      if ($resp && isset($resp->access_token))
      {
        $account_id = newid('i');
        $source_uri = 'instagram_account:' . $account_id;

        // As a requirement, we remove this instagram account from other users if they connected it
        collection('users')->update(
          [],
          [
            '$pull' => [
              'connected_accounts' => [
                'type' => 'instagram',
                'screen_name' => $resp->user->username
              ]
            ]
          ],
          ['multiple' => true]
        );

        $collection_uri = 'instagram:' . $resp->user->username;

        $res = collection('users')->update(
          array('_id' => $this->users->id()),
          array(
            '$set' => array(
              'full_name' => $resp->user->full_name
              // TODO: Update only if connected accounts is 0
              // 'nickname' => $resp->user->username,
              // 'avatar.small'  => $resp->user->profile_picture,
              // 'avatar.medium' => $resp->user->profile_picture,
              // 'avatar.high'   => $resp->user->profile_picture
            ),
            '$push' => array(
              'connected_accounts' => array(
                'id' => $source_uri,
                'processed_at' => 0,
                'connected_at' => time(),
                'type' => 'instagram',
                'avatar' => $resp->user->profile_picture,
                'screen_name' => $resp->user->username,
                'full_name' => $resp->user->full_name,
                'access_token' => array(
                  'oauth_token' => $resp->access_token,
                  'user_id' => $resp->user->id,
                ),
                'following' => array(
                  'count' => 0,
                  'updated_at' => 0
                )
              )
            )
          )
        );

        if ($res)
        {
          $this->users->reauthenticate();

          $res = $this->sources->add_instagram_category($account_id, $resp->user->username, $resp->user->full_name);

          // Also adds myself as a source
          $this->sources->add_instagram_person($res['id'], $resp->user);

          if (!$res)
          {
            return $this->json(422, ['errors' => ['Cannot add the twitter source']]);
          }

          $this->load->model('model_feeds', 'feeds');
          $this->feeds->update_instagram_followers($this->users->get('_id'));

          // Create a collection with this data
          $this->load->model('model_collections', 'collections');
          $collection = $this->collections->create([
            'name' => 'Instagram',
            'type' => 'instagram',
            'sources' => [$res['source_uri']],
            'account_key' => $collection_uri
          ], false);

          $cb = $this->input->cookie('callback');

          if ($cb)
          {
            return redirect($cb);
          }
          else
          {
            return $this->load->view('connected-account', ['type' => 'instagram']);
          }
        }
        else
        {
          return $this->json(422, ['errors' => ['Cannot add the instagram account']]);
        }
      }
    }

    $this->input->set_cookie([
      'name' => 'callback',
      'value' => $this->input->get('d'),
      'expire' => 0
    ]);

    $this->users->store_token();

    $this->meta_redirect($this->instagram->getLoginUrl(['basic', 'likes', 'comments']));
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
                'type' => 'twitter',
                'screen_name' => $user->screen_name
              ]
            ]
          ],
          ['multiple' => true]
        );

        $collection_uri = 'twitter:' . $user->screen_name;

        unset($access_token['screen_name']);

        $res = collection('users')->update(
          array('_id' => $this->users->id()),
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
                'screen_name' => $user->screen_name,
                'full_name' => $user->name,
                'avatar' => $user->profile_image_url_https,
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
          $this->users->reauthenticate();

          $res = $this->sources->add_twitter_category($account_id, '@' . $user->screen_name, $user->name);

          // Also adds myself as a source
          $this->sources->add_twitter_person($res['id'], [
            'id' => $user->id,
            'name' => $user->name,
            'screen_name' => $user->screen_name . ' (you)',
            'avatar' => $user->profile_image_url_https
          ]);

          if (!$res)
          {
            return $this->json(422, ['errors' => ['Cannot add the twitter source']]);
          }

          $this->load->model('model_feeds', 'feeds');
          $this->feeds->update_twitter_followers($this->users->get('_id'));

          // Create a collection with this data
          $this->load->model('model_collections', 'collections');
          $collection = $this->collections->create([
            'name' => '@' . $user->screen_name,
            'type' => 'twitter',
            'sources' => [$res['source_uri']],
            'account_key' => $collection_uri
          ], false);

          $cb = $this->input->cookie('callback');

          if ($cb)
          {
            return redirect($cb);
          }
          else
          {
            return $this->load->view('connected-account', ['type' => 'twitter']);
          }
        }
        else
        {
          return $this->json(422, ['errors' => ['Cannot add the twitter account']]);
        }
      }
      else
      {
        return show_error('Could not verify your Twitter account.');
      }
    }

    $this->input->set_cookie([
      'name' => 'callback',
      'value' => $this->input->get('d'),
      'expire' => 0
    ]);

    $this->meta_redirect($this->twitter->get_loginurl($token));
  }

  public function add_feedly_account()
  {
    if (!$this->require_token()) return;

    $this->load->helper('url');
    $this->load->library('feedly');

    $token = $this->users->token();

    if ($code = $this->input->get('code'))
    {
      $res = $this->feedly->getAccessToken($code);

      if (isset($res->errorCode))
      {
        return show_error(ucfirst($res->errorMessage), 401, 'Feedly Connect');
      }

      $this->feedly->setAccessToken($res->access_token);

      $user = $this->feedly->getProfile();

      if (isset($user->errorCode))
      {
        return show_error(ucfirst($user->errorMessage), 401, 'Feedly Connect: User profile');
      }

      $user->full_name = $user->givenName . ' ' . $user->familyName;

      $account_id = newid('fdl');
      $source_uri = 'feedly_account:' . $account_id;

      // As a requirement, we remove this feedly account from other users if they connected it
      collection('users')->update(
        [],
        [
          '$pull' => [
            'connected_accounts' => [
              'type' => 'feedly',
              'access_token.user_id' => $res->id
            ]
          ]
        ],
        ['multiple' => true]
      );

      $collection_uri = 'feedly:' . $res->id;

      $op = collection('users')->update(
        array('_id' => $this->users->id()),
        array(
          // '$set' => array(
          //   'full_name' => $user->name,
          //   'nickname' => $user->screen_name
          // ),
          '$push' => array(
            'connected_accounts' => array(
              'id' => $source_uri,
              'processed_at' => 0,
              'connected_at' => time(),
              'type' => 'feedly',
              'access_token' => [
                'user_id' => $res->id,
                'token' => $res->access_token,
                'refresh_token' => $res->refresh_token,
                'expires_at' => time() + $res->expires_in
              ],
              'full_name' => $user->full_name,
              'screen_name' => $user->full_name,
              'avatar' => $user->picture,
              'opml' => [
                'processed_at' => 0,
                'categories_count' => 0,
                'feeds_count' => 0
              ]
            )
          )
        )
      );

      if ($op)
      {
        $this->users->reauthenticate();

        $xml = $this->feedly->getOpml();

        if ($xml)
        {
          $n_cat = 0;
          $n_feed = 0;

          $user_id = $this->users->id();

          $this->load->model('model_collections', 'collections');

          foreach ($xml->body->outline as $outline)
          {
            $title = (string) $outline['title'];

            $cat = collection('user_categories')->findOne(
              array(
                'user_id' => $user_id,
                'text' => $title
              ),
              array(
                'id' => true,
                'source_uri' => true
              )
            );

            $exist = $cat ? true : false;

            if (!$exist)
            {
              $cat = $this->sources->add_feed_category($title);
            }

            if ($cat && isset($cat['id']))
            {
              foreach ($outline->outline as $rss)
              {
                if ($rss['type'] != 'rss') continue;

                $this->sources->add_feed($cat['id'], (string)$rss['title'], (string)$rss['xmlUrl']);
                $n_feed++;
              }

              $n_cat++;
            }

            if (!$exist)
            {
              $this->collections->create([
                'name' => $title,
                'type' => 'feedly',
                'sources' => [$cat['source_uri']],
                'account_key' => $collection_uri . ':' . $cat['id']
              ], false);
            }
          }

          collection('users')->update(
            array(
              '_id' => $user_id,
              'connected_accounts.id' => $source_uri
            ),
            array(
              '$set' => array(
                'connected_accounts.$.opml.processed_at' => time(),
                'connected_accounts.$.opml.categories_count' => $n_cat,
                'connected_accounts.$.opml.feeds_count' => $n_feed
              )
            )
          );
        }

        $cb = $this->input->cookie('callback');

        if ($cb)
        {
          return redirect($cb);
        }
        else
        {
          return $this->load->view('connected-account', ['type' => 'feedly']);
        }
      }
      else
      {
        return show_error('Please try later');
      }
    }

    $this->input->set_cookie([
      'name' => 'callback',
      'value' => $this->input->get('d'),
      'expire' => 0
    ]);

    $this->users->store_token();

    $this->meta_redirect($this->feedly->getLoginUrl(current_url()));
  }
}