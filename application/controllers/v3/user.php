<?php
/**
 * User controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class User_Controller extends Cronycle_Controller
{
  public function index()
  {
    if (!$this->require_token()) return;

    $this->json(200, $this->user->find($this->user->get('_id')));
  }

  public function check_email()
  {
    $this->load->helper('email');

    $email = $this->input->get_post('email');

    if (valid_email($email))
    {
      if (!$this->user->email_in_use($email))
      {
        $this->json(200);
      } else {
        $this->json(409, array('errors' => ['Email is already taken']));
      }
    } else {
      $this->json(422, array('errors' => ['Email is not valid']));
    }
  }

  public function sign_in()
  {
    if ($this->method != 'post') return;

    $this->set_body_request();

    if ($this->request->user)
    {
      $user = $this->user->sign_in(
        $this->request->user->email,
        $this->request->user->password
      );

      if ($user)
      {
        return $this->json(200, $user);
      }
    }

    $this->json(406, array('errors' => ['User not found or credentials were invalid']));
  }

  public function sign_up()
  {
    if ($this->method != 'post') return;

    $this->set_body_request();

    if (!$this->request)
    {
      return $this->json(400, array('errors' => ['Bad request']));
    }

    $this->load->helper('email');

    $data = $this->request->user;

    if (!isset($data->email) || !valid_email($data->email))
    {
      return $this->json(422, array('errors' => ['Email is not valid']));
    }

    if ($this->user->email_in_use($data->email))
    {
      return $this->json(409, array('errors' => ['Email is already taken']));
    }

    if (!isset($data->password) || strlen($data->password) < 8)
    {
      return $this->json(400, array('errors' => ['Password not provided or too short']));
    }

    $resp = $this->user->sign_up(array(
      'email' => $data->email,
      'password' => $data->password,
      'full_name' => $data->full_name
    ));

    if ($resp)
    {
      $this->json(200, $resp);

    } else {
      return $this->json(500, array('errors' => ['Could not create the user']));
    }
  }
}