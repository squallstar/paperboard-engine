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
    $pipeline = [
      ['$unwind' => '$tags'],
      ['$group' => [
        '_id' => '$tags',
        'value' => ['$sum' => 1]
      ]],
      ['$sort' => ['value' => -1]],
      ['$limit' => 30]
    ];

    $res = collection('collections')->aggregate($pipeline)['result'];

    $tags = [];

    foreach ($res as $tag)
    {
      $tags[] = $tag['_id'];
    }

    $this->json(200, $tags);
  }
}