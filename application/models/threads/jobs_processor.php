<?php
/**
 * thread mailer model
 *
 * @package     Paperboard
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Jobs_processor extends CI_Model
{
  public function __construct()
  {
    parent::__construct();

    $this->load->model('model_mailer', 'mailer');
    $this->mailer->enqueue(false);
  }

  public function process()
  {
    foreach (collection('jobs')->find()->sort(['added_at' => -1]) as $job)
    {
      _log("Processing job " . $job['_id'] . ' of type ' . $job['type'] . '.');

      $processed = false;

      switch ($job['type'])
      {
        case 'email':
          $this->mailer->process_job($job['specs']);
          $processed = true;
          break;
      }

      if (!$processed)
      {
        _log("Cannot process job " . $job['_id']);
      }
      else
      {
        _log("Job " . $job['_id'] . ' processed.');
        collection('jobs')->remove(['_id' => $job['_id']], ['justOne' => true]);
      }
    }
  }
}