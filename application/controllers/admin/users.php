<?php
/**
 * Admin controller for users
 *
 * @package     Hhvm
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Users_Controller extends CI_Controller
{
  public function __construct()
  {
    parent::__construct();

    $this->load->helper('admin');
  }

  public function index()
  {
    $this->load->model('model_users', 'users');

    $delete = $this->input->get('delete');

    $flash_message = false;

    if ($delete)
    {
      $res = $this->users->delete_user($delete);

      if ($res)
      {
        $flash_message = 'Removed ' . $res['users'] . ' users and ' . $res['feeds'] . ' feeds';
      }
    }

    $unlink = $this->input->get('unlink');

    if ($unlink)
    {
      $res = $this->users->unlink_account_from_user($unlink, $this->input->get('account'));

      if ($res)
      {
        $flash_message = 'The account has been unlinked from the user.';
      }
    }

    load_admin_view('admin/users/list', [
      'flash' => $flash_message,
      'client_url' => $this->config->item('client_base_url'),
      'users' => collection('users')->find()->sort(['created_at' => -1])
    ]);
  }
}