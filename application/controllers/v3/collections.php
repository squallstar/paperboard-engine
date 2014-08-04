<?php
/**
 * Collections controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Collections_Controller extends Cronycle_Controller
{
  public function __construct()
  {
    parent::__construct();
    $this->load->model('model_collections', 'collections');
  }

  public function index()
  {
    if (!$this->require_token()) return;

    if ($this->method == 'post') return $this->create();

    $this->json(200, array_values(iterator_to_array(collection('collections')->find(
      array(
        'user.id' => $this->users->get('_id')
      ),
      array(
        '_id' => false,
        'sources' => false
      )
    ))));
  }

  public function create()
  {
    $this->set_body_request();

    if (!isset($this->request['collection']))
    {
      return $this->json(400);
    }

    $res = $this->collections->save($this->request['collection']);

    if ($res)
    {
      $this->json(201, $res);
    } else {
      $this->json(422, array('errors' => ['Cannot create the collection']));
    }
  }

  public function reorder()
  {
    if (!$this->require_token() || $this->method != 'post') return;

    $this->set_body_request();

    if (is_array($this->request['collection_ids']))
    {
      $pos = 0;

      foreach ($this->request['collection_ids'] as $id)
      {
        if ($id != 'favourite_collection')
        {
          $this->collections->update($id, array('position' => $pos));
        }
        else
        {
          $this->users->update_current(array(
            'favourite_collection_position' => $pos
          ));
        }

        $pos++;
      }

      die;

    } else {
      $this->json(422, array('errors' => ['Collection IDs are required']));
    }
  }

  public function view($collection_id)
  {
    $collection = $this->collections->find($collection_id);

    if ($collection)
    {
      $this->json(200, $collection);
    } else {
      $this->json(404);
    }
  }

  public function view_links($collection_id)
  {
    if (strpos($collection_id, 'p') !== 0) {
      if (!$this->require_token()) return;
    }

    $this->json(200, [
      [
        'id' => 1,
        'url' => 'http://cronycle.com',
        'name' => 'Lorem ipsum',
        'description' => 'dolor sit amet',
        'content' => '<p>Looorem ipsum!</p>',
        'sources' => [
          [
            'full_name' => 'Cronycle',
            'screen_name' => 'wearecronycle',
            'profile_image_url' => 'https://pbs.twimg.com/profile_images/378800000851024662/11a229c4131c81e81195ac8be9aff116_normal.jpeg',
            'published_at' => time(),
            'external_id' => 1,
            'type' => 'TwitterUser'
          ]
        ]
      ]
    ]);
  }

  public function favourite_collection_links()
  {
    if (!$this->require_token()) return;

    $this->json(200, array());
  }
}