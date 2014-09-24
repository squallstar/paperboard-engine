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

class Parsers_Controller extends CI_Controller
{
  public function __construct()
  {
    parent::__construct();

    $this->load->helper('admin');
  }

  public function expand()
  {
    $res = false;

    $url = $this->input->post('url');

    if ($url)
    {
      $this->load->model('model_articles_expander', 'expander');

      $articles = [
        ['id' => 'demo', 'url' => $url]
      ];

      $this->expander->expand($articles, false);
      ob_clean();

      $res = $articles[0];
    }

    load_admin_view('admin/parsers/expand', [
      'article' => $res
    ]);
  }

  public function index()
  {
    $flash_message = false;

    $delete = $this->input->get('delete');
    if ($delete)
    {
      $id = new MongoId($delete);
      if (collection('parsers')->remove(['_id' => $id], ['justOne' => true]))
      {
        $flash_message = 'The rule has been removed.';
      }
    }

    $check = $this->input->get('check');
    if ($check)
    {
      $id = new MongoId($check);
      $rule = collection('parsers')->findOne(['_id' => $id]);

      if ($rule)
      {
        $this->load->model('model_articles_expander', 'expander');

        $articles = [
          ['id' => $rule['_id']->{'$id'}, 'url' => $rule['example']]
        ];

        $res = $this->expander->expand($articles, false);
        ob_clean();

        if ($res && isset($articles[0]['content']))
        {
          collection('parsers')->update(['_id' => $id], [
            '$set' => [
              'checked' => true,
              'checked_at' => time(),
              'example_fetch' => $articles[0]
            ]
          ]);
        }
      }
    }

    if ($this->input->post('host'))
    {
      $res = collection('parsers')->insert(
        [
          'checked_at' => 0,
          'checked' => false,
          'added_at' => time(),
          'host' => trim($this->input->post('host')),
          'example' => trim($this->input->post('example')),
          'xpath' => trim($this->input->post('xpath')),
          'cleanup' => $this->input->post('cleanup') ? true : false
        ]
      );

      if ($res)
      {
        $flash_message = 'The rule has been added';
      }
    }

    load_admin_view('admin/parsers/list', [
      'flash' => $flash_message,
      'rules' => collection('parsers')->find()->sort(['checked' => 1, 'host' => 1])
    ]);
  }

  public function update($id)
  {
    if (!$this->input->is_ajax_request())
    {
      exit('No direct route access allowed');
    }

    $data = [
      'host' => $this->input->post('host'),
      'example' => $this->input->post('example'),
      'cleanup' => $this->input->post('cleanup') == 'true' ? true : false,
      'checked' => false,
      'checked_at' => 0,
      'xpath' => $this->input->post('xpath')
    ];

    $res = collection('parsers')->update(
      ['_id' => new MongoId($id)],
      ['$set' => $data, '$unset' => ['example_fetch' => 1]]
    );

    echo $res ? 1 : 0;
  }
}