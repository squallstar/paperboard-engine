<?php
/**
 * runner model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Model_runner extends CI_Model
{
  public function update_links_counters()
  {
    $this->load->model('model_collections', 'collections');

    $collections = collection('collections')->find(
      [],
      ['id' => true, 'feeds' => true, 'sources' => true]
    );

    $i = 0;

    foreach ($collections as $collection)
    {
      if (collection('collections')->update(
        ['id' => $collection['id']],
        [
          '$set' => [
            'total_links_count' => $this->collections->links($collection, FALSE)->count()
          ]
        ]
      )) $i++;;
    }

    unset($collections);

    return $i;
  }
}