<?php
/**
 * Admin controller for boards
 *
 * @package     Hhvm
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Boards_Controller extends CI_Controller
{
  public function __construct()
  {
    parent::__construct();

    $this->load->helper('admin');
  }

  public function index()
  {
    $flash_message = false;
    $conditions = [];

    $user = $this->input->get('user');


    if ($user)
    {
      $conditions['user.id'] = intval($user);
    }

    $boards = collection('collections')->find($conditions, ['feeds' => false])->sort(['last_updated_at' => -1]);

    load_admin_view('admin/boards/list', [
      'flash' => $flash_message,
      'boards' => $boards
    ]);
  }

  public function update($id)
  {
    $id = intval($id);

    if (!$this->input->is_ajax_request())
    {
      exit('No direct route access allowed');
    }

    $data = [];

    if ($tags = $this->input->post('tags'))
    {
      $data['tags'] = [];
      foreach (explode(',', $tags) as $tag)
      {
        $data['tags'][] = trim($tag);
      }
    }

    if ($featured = $this->input->post('featured'))
    {
      $data['featured'] = $featured == 'true' ? true : false;
    }

    $res = collection('collections')->update(
      ['id' => $id],
      ['$set' => $data]
    );

    echo $res ? 1 : 0;
  }
}