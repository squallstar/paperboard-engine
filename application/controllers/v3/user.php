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
    if ($this->method != 'get') return;

    if (!$this->require_token()) return;

    $this->json(200, $this->users->find($this->users->get('_id')));
  }

  public function check_email()
  {
    if ($this->method != 'get' && $this->method != 'post') return;

    $this->load->helper('email');

    if ($this->method == 'get')
    {
      $email = $this->input->get_post('email');
    }
    else
    {
      $this->set_body_request();
      $email = $this->request['email'];
    }

    if (valid_email($email))
    {
      if (!$this->users->email_in_use($email))
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

    if ($this->request['user'])
    {
      $user = $this->users->sign_in(
        $this->request['user']['email'],
        $this->request['user']['password']
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

    $data = $this->request['user'];

    if (!isset($data['email']) || !valid_email($data['email']))
    {
      return $this->json(422, array('errors' => ['Email is not valid']));
    }

    if ($this->users->email_in_use($data['email']))
    {
      return $this->json(409, array('errors' => ['Email is already taken']));
    }

    if (!isset($data['password']) || strlen($data['password']) < 8)
    {
      return $this->json(400, array('errors' => ['Password not provided or too short']));
    }

    $resp = $this->users->sign_up(array(
      'email' => $data['email'],
      'password' => $data['password'],
      'full_name' => $data['full_name']
    ));

    if ($resp)
    {
      $this->json(200, $resp);

    } else {
      return $this->json(500, array('errors' => ['Could not create the user']));
    }
  }

  public function bucket($key)
  {
    if (!$this->require_token()) return;

    if ($this->method == 'put')
    {
      $this->set_body_request();

      $data = [];
      $data['bucket.' . $key] = $this->request['value'];

      if ($this->users->update_current($data))
      {

      }
    }
  }

  public function avatar()
  {
    if (!$this->require_token() || $this->method != 'post') return;

    if (!isset($_FILES['file']))
    {
      return $this->json(422, ['errors' => ['File not provided']]);
    }

    $file = & $_FILES['file'];

    if (!in_array($file['type'], ['image/jpeg', 'image/png']))
    {
      return $this->json(422, ['errors' => ['The given file is not a JPEG or PNG image']]);
    }

    list($width, $height, $type, $attr) = getimagesize($file['tmp_name']);

    if ($width < 100 || $height < 100)
    {
      return $this->json(422, ['errors' => ['The avatar width and height must be greater than 100px']]);
    }

    $this->load->model('model_images_processor', 'images');

    $folder = 'avatars/' . $this->users->id();

    $res = $this->users->update_current([
      'avatar' => [
        'small' => $this->images->upload_image($folder, $file['tmp_name'], 30, 30),
        'medium' => $this->images->upload_image($folder, $file['tmp_name'], 60, 60),
        'high' => $this->images->upload_image($folder, $file['tmp_name'], 150)
      ]
    ]);

    if ($res)
    {
      $this->json(200);
    }
    else
    {
      $this->json(422);
    }
  }
}
