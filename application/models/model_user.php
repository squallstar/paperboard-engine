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

Class Model_user extends CI_Model
{
  private $_user;

  public function __construct()
  {
    parent::__construct();
  }

  private function _generate_token($user_id = '')
  {
    return $user_id . md5($user_id . microtime());
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

  public function sign_in($email, $password)
  {
    $u = collection('users')->findOne(
      array(
        'email' => $email,
        'password' => $password
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

  public function authenticate($token)
  {
    $this->_user = collection('users')->findOne(
      array(
        'auth_token' => $token
      ),
      array('_id')
    );

    return $this->_user ? true : false;
  }

  public function get($key = null)
  {
    if ($key)
    {
      return $this->_user[$key];
    }

    return $this->_user;
  }

  public function sign_up($data)
  {
    $data = array(
      '_id'            => campid('u'),
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

      'bucket' => array(

      ),

      'connected_accounts' => array(

      ),

      'favourite_collection_position' => 0,
      'has_password' => true,
      'has_to_wait' => false,
      'is_pro' => true,
      'marketing_opt_in' => true,
      'needs_to_subscribe' => false,
      'total_collections_count' => 0,
      'total_links_count' => 0
    );

    $res = collection('users')->save($data, array('safe' => true));

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
        'password'   => false
      )
    );

    if ($u)
    {
      $u['id'] = $u['_id'];
      unset($u['_id']);
    }

    return $u;
  }
}