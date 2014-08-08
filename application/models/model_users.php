<?php
/**
 * user model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_users extends CI_Model
{
  private $_user;
  private $_auth_token;

  public function __construct()
  {
    parent::__construct();
  }

  private function _generate_token($user_id = '')
  {
    return $user_id . md5($user_id . microtime());
  }

  public function set_user($user)
  {
    $this->_user = $user;
  }

  public function login($email, $password)
  {
    $user = collection('users')->findOne(
      array(
        'email'    => $email,
        'password' => md5($password)
      ),
      array('_id')
    );

    if ($user) {
      $token = $this->_generate_token($user->_id);

      $res = collection('users')->update(
        array('_id' => $user->_id),
        array(
          '$set' => array(
            'auth_token'    => $token,
            'logged_at'     => time()
          )
        )
      );

      if ($res) {
        $user->auth_token = $token;
        $this->_user = $user;

        return $user;
      }
    }
  }

  public function update_current($data = array())
  {
    return collection('users')->update(
      array('_id' => $this->get('_id')),
      array('$set' => $data)
    );
  }

  public function sign_in($email, $password)
  {
    $u = collection('users')->findOne(
      array(
        'email' => $email,
        'password' => md5($password)
      ),
      array(
        'password' => false
      )
    );

    if ($u)
    {
      $data = array(
        'auth_token' => $u['auth_token'],
        'user' => $u
      );

      unset($data['user']['auth_token']);

      return $data;
    }
  }

  public function load_user()
  {
    if ($this->_user) return true;
    return $this->authenticate($this->input->get_post('auth_token'));
  }

  public function authenticate($token)
  {
    $this->_user = collection('users')->findOne(
      array(
        'auth_token' => $token
      ),
      array('_id', 'full_name', 'avatar.small')
    );

    if ($this->_user)
    {
      $this->_auth_token = $token;
      return true;
    }

    return false;
  }

  public function get($key = null)
  {
    if ($key)
    {
      return $this->_user[$key];
    }

    return $this->_user;
  }

  public function token()
  {
    return $this->_auth_token;
  }

  public function sign_up($data)
  {
    $data = array(
      '_id'            => next_id('user'),
      'created_at'     => time(),
      'logged_at'      => '',
      'email'          => $data['email'],
      'password'       => md5($data['password']),
      'full_name'      => $data['full_name'],
      'nickname'       => $data['full_name'],
      'auth_token'     => $this->_generate_token(),

      'avatar' => array(
        'high'   => 'http://cronycle-staging-avatar.s3.amazonaws.com/uploads/high_cronycle-logo.png',
        'medium' => 'http://cronycle-staging-avatar.s3.amazonaws.com/uploads/medium_cronycle-logo.png',
        'small'  => 'http://cronycle-staging-avatar.s3.amazonaws.com/uploads/small_cronycle-logo.png'
      ),

      'bucket' => new stdClass,
      'connected_accounts' => [],

      'favourite_collection_position' => 99,
      'favourites' => [],

      'has_password' => true,
      'has_to_wait' => false,
      'is_pro' => true,
      'marketing_opt_in' => true,
      'needs_to_subscribe' => false,
      'total_collections_count' => 0,
      'total_links_count' => 0
    );

    $res = collection('users')->save($data);

    if ($res)
    {
      $user = array(
        'auth_token' => $data['auth_token'],
        'user'       => $this->find($data['_id'], true)
      );

      return $user;
    }

    return false;
  }

  public function email_in_use($email)
  {
    return collection('users')->findOne(array('email' => $email)) ? true : false;
  }

  public function find($id, $expand = true)
  {
    $u = collection('users')->findOne(
      array('_id' => $id),
      array(
        'auth_token' => false,
        'password'   => false,
        'connected_accounts.access_token' => false,
        'favourites' => false
      )
    );

    if ($u)
    {
      $u['id'] = $u['_id'];
      unset($u['_id']);
    }

    return $u;
  }

  public function add_favourite($article_id)
  {
    return collection('users')->update(
      ['_id' => $this->_user['_id']],
      [
        '$addToSet' => [
          'favourites' => [
            'id' => $article_id,
            'favourited_at' => time()
          ]
        ]
      ]
    );
  }

  public function remove_favourite($article_id)
  {
    return collection('users')->update(
      ['_id' => $this->_user['_id']],
      [
        '$pull' => [
          'favourites' => [
            'id' => $article_id
          ]
        ]
      ]
    );
  }

  public function get_favourites()
  {
    $res = collection('users')->findOne(['_id' => $this->_user['_id']], ['favourites']);

    return $res ? $res['favourites'] : array();
  }
}