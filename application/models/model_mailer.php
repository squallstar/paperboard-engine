<?php
Class Model_mailer extends CI_Model
{
	private $_tpl = '';

	public function __construct()
	{
		parent::__construct();

		$this->load->helper('url');
	}

	private function _sendto($to = '', $to_name = '', $subject = '', $reply = '')
	{
		$this->load->library('email');

		$this->email->from('support@paperboard.me', 'Paperboard');
		$this->email->to($to, $to_name);
		$this->email->subject($subject);
		$this->email->message($this->_tpl);

		try
		{
			return $this->email->send();
		}
		catch (Exception $e)
		{
			log_message('error', $e->getMessage());
			return false;
		}
	}

	private function _template($name = '', $data = array(), $layout = 'default')
	{
		$data['_template'] = 'emails/' . $name;
		$this->_tpl = $this->load->view('emails/layouts/' . $layout, $data, TRUE);
	}

	public function send_welcome($user)
	{
		$this->_template('welcome', ['link' => base_url('confirm-email/' .$user['_id'] . '/' . $user['optin_token'])]);
		return $this->_sendto($user['email'], $user['full_name'], 'Welcome to Paperboard!');
	}
}