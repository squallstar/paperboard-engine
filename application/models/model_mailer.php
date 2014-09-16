<?php
Class Model_mailer extends CI_Model
{
	private $_tpl = '';

	private $_queue_enabled = true;
	private $_queue_data;

	public function __construct()
	{
		parent::__construct();

		$this->load->helper('url');
	}

	public function enqueue($use_queue)
	{
		$this->_queue_enabled = $use_queue ? true : false;
	}

	private function _enqueue_email()
	{
		if ($this->_queue_data)
		{
			return collection('jobs')->insert(
				[
					'type' => 'email',
					'added_at' => time(),
					'priority' => 'high',
					'specs' => $this->_queue_data
				]
			);
		}
	}

	public function process_job($specs)
	{
		$this->_template($specs['template'], $specs['bindings']);
		return $this->_sendto($specs['recipient'], $specs['recipient_name'], $specs['subject']);
	}

	private function _sendto($to = '', $to_name = '', $subject = '')
	{
		if ($this->_queue_enabled)
		{
			$this->_queue_data['recipient'] = $to;
			$this->_queue_data['recipient_name'] = $to_name;
			$this->_queue_data['subject'] = $subject;

			return $this->_enqueue_email();
		}

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
		if ($this->_queue_enabled)
		{
			$this->_queue_data = [
				'template' => $name,
				'bindings' => $data
			];

			return;
		}

		$data['_template'] = 'emails/' . $name;
		$this->_tpl = $this->load->view('emails/layouts/' . $layout, $data, TRUE);
	}

	public function send_welcome($user)
	{
		$this->_template('welcome', ['link' => base_url('confirm-email/' .$user['_id'] . '/' . $user['optin_token'])]);
		return $this->_sendto($user['email'], $user['full_name'], 'Welcome to Paperboard!');
	}
}