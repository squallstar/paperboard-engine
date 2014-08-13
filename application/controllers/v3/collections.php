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

    $include_links = $this->input->get('include_links');

    $collections = $this->collections->find_mine($include_links);

    if ($include_links)
    {
      $how_many = intval($this->input->get('include_first'));

      $favourites = $this->users->get_favourites(true);
      $has_favourites = count($favourites) > 0;

      foreach ($collections as &$collection)
      {
        $collection['links'] = iterator_to_array($this->collections->links($collection, 10), false);
        unset($collection['feeds']);

        if ($has_favourites)
        {
          foreach ($collection['links'] as &$link)
          {
            if (in_array($link['id'], $favourites)) $link['is_favourited'] = true;
          }
        }

        $how_many--;
        if ($how_many == 0) break;
      }

      unset($collection);
    }

    $this->json(200, $collections);
  }

  public function create()
  {
    $this->set_body_request();

    if (!isset($this->request['collection']))
    {
      return $this->json(400);
    }

    $res = $this->collections->create($this->request['collection']);

    if ($res)
    {
      $this->json(201, $res);
    } else {
      $this->json(422, array('errors' => ['Cannot create the collection']));
    }
  }

  public function update($collection_id)
  {
    if (!$this->require_token()) return;

    $this->set_body_request();

    if (!isset($this->request['collection']))
    {
      return $this->json(400);
    }

    $res = $this->collections->update($collection_id, $this->request['collection'], true);

    if ($res)
    {
      $this->json(200, $res);
    } else {
      $this->json(422, array('errors' => ['Cannot update the collection']));
    }
  }

  public function publish($collection_id)
  {
    if (!$this->require_token()) return;

    if ($this->method == 'delete')
    {
      if ($this->collections->update($collection_id, array(
        'publicly_visible' => false,
        'category' => null
      )))
      {
        return $this->json(200);
      }
      else
      {
        return $this->json(500);
      }
    }
    else if ($this->method != 'post') return;

    $this->set_body_request();

    if (!isset($this->request['category_id']))
    {
      return $this->json(422);
    }

    $slug = $this->request['category_id'];
    $cat = collection('categories')->findOne(
      array('slug' => $slug)
    );

    if (!$cat)
    {
      return $this->json(400, ['errors' => ['Category not found']]);
    }

    if ($this->collections->update($collection_id, array(
      'publicly_visible' => true,
      'category' => array(
        'id'   => $cat['id'],
        'name' => $cat['name'],
        'slug' => $cat['slug']
      ),
      'description' => strip_tags($this->request['description'])
    )))
    {
      return $this->json(200);
    }
    else
    {
      return $this->json(500);
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
          $this->collections->update_single_field($id, 'position', $pos);
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
    if ($this->method == 'put') return $this->update($collection_id);

    $collection = $this->collections->find($collection_id, array(
      '_id' => false,
      'sources' => false,
      'feeds' => false
    ));

    if ($collection)
    {
      if ($this->method == 'delete')
      {
        if ($collection['owned_collection'])
        {
          if ($this->collections->delete($collection['id']))
          {
            return $this->json(200);
          }
          else
          {
            return $this->json(422, ['errors' => ['Cannot delete the collection']]);
          }
        }
        else
        {
          if ($this->collections->unfollow($collection['id']))
          {
            return $this->json(200);
          }
          else
          {
            return $this->json(422, ['errors' => ['Cannot unfollow the collection']]);
          }
        }
      }

      $this->json(200, $collection);
    }
    else
    {
      $this->json(404, ['errors' => ['The collection was not found']]);
    }
  }

  public function view_links($collection_id)
  {
    $collection = $this->collections->find($collection_id, array(
      'feeds' => true,
      'filters' => true
    ));

    if ($collection)
    {
      $links = $this->collections->links(
        $collection,
        $this->input->get('per_page'),
        $this->input->get('max_timestamp'),
        $this->input->get('min_timestamp')
      );

      unset($collection);

      $this->json(200, iterator_to_array($links, false));
    }
    else
    {
      $this->json(404, ['errors' => ['The collection was not found']]);
    }
  }

  public function follow($collection_id)
  {
    if (!$this->require_token() || $this->method != 'post') return;

    if ($this->collections->follow($collection_id))
    {
      $this->json(200);
    }
    else
    {
      $this->json(404);
    }
  }

  public function search_links()
  {
    if (!$this->require_token()) return;

    $this->load->model('model_sources', 'sources');

    $query = $this->input->get('search_query');

    $cond = [];

    if (strpos($query, '@') === 0)
    {
      // Search by twitter author
      $author = collection('feeds')->findOne(
        ['title' => new MongoRegex("/$query/i")],
        ['_id' => true]
      );

      if ($author)
      {
        $cond['feeds'] = [$author['_id']->{'$id'}];
      }
    }

    if (count($cond) == 0)
    {
      $cond['filters'] = [
        [
          'context' => 'keywords',
          'filter_value' => $query
        ]
      ];
    }

    // Include the line below in the feeds to search only using the user sources
    //'feeds' => $this->sources->tree_ids()

    $links = $this->collections->links(
      $cond,
      $this->input->get('per_page'),
      $this->input->get('max_timestamp'),
      $this->input->get('min_timestamp')
    );

    $this->json(200, iterator_to_array($links, false));
  }

  public function favourite_collection()
  {
    if (!$this->require_token()) return;

    $this->set_body_request();

    $id = $this->request['id'];

    if (!$id) return $this->json(400, ['errors' => ['Article ID is required']]);

    $res = $this->method == 'post' ? $this->users->add_favourite($id) : $this->users->remove_favourite($id);

    $this->json($res ? 200 : 422);
  }

  public function favourite_collection_links()
  {
    if (!$this->require_token()) return;

    $favourites = $this->users->get_favourites(true);

    if (count($favourites))
    {
      $favourite_collection = [
        'article_ids' => &$favourites
      ];

      // TODO: favourite order should be calcolated prior to the query (using per_page, min_timestamp, etc)

      $links = iterator_to_array($this->collections->links(
        $favourite_collection,
        $this->input->get('per_page'),
        $this->input->get('max_timestamp'),
        $this->input->get('min_timestamp')
      ), false);

      foreach ($links as &$link)
      {
        $link['is_favourited'] = true;
      }

      $this->json(200, $links);
    }
    else
    {
      $this->json(200, []);
    }
  }
}