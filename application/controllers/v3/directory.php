<?php
/**
 * Directory controller
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Directory_Controller extends Cronycle_Controller
{
  public function index()
  {
    $this->json(200, iterator_to_array(
      collection('categories')->find(
        array(),
        array('_id' => false)
      )
    ));
  }

  public function tags()
  {
    $this->load->model('model_tags', 'tags');

    $filter = [];

    if ($this->input->get('filter')) $filter = explode(',', $this->input->get('filter'));

    $ids = [];

    $limit = 25;
    $tags = [];

    if (count($filter))
    {
      $tags = $this->tags->suggested(15, $filter);

      $limit -= count($tags);

      foreach ($tags as $tag)
      {
        $filter[] = $tag['name'];
      }
    }

    if ($limit > 0)
    {
      $tags = array_merge($tags, $this->tags->popular($limit, $filter));
    }

    $this->json(200, $tags);
  }
}