<?php
/**
 * Core controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Cronycle_Controller extends CI_Controller
{
	public function __construct()
	{
		parent::__construct();

    $this->method = strtolower($this->input->server('REQUEST_METHOD'));

		//These models are always available
		$this->load->model('model_users', 'users');
	}

  protected function load_user()
  {
    return $this->users->load_user();
  }

  protected function require_token()
  {
    if (!$this->load_user())
    {
      $this->json(400, array('errors' => ['Auth token not valid']));
      return false;
    }

    return true;
  }

  protected function set_body_request()
  {
    $this->request = json_decode(file_get_contents('php://input'), true);
  }

  protected function json($code = 200, $data = null)
  {
    if ($data === null) $data = new stdClass;

    $this->output->set_content_type('application/json')
         ->set_status_header($code)
         ->set_output(json_encode($data));
  }
}