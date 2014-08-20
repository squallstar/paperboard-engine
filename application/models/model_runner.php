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
  public function update_collections_metadata()
  {
    $this->load->model('model_collections', 'collections');

    $collections = collection('collections')->find(
      [],
      ['id' => true, 'feeds' => true, 'sources' => true, 'cover_asset.fixed' => true]
    );

    $i = 0;

    foreach ($collections as $collection)
    {
      $data = [
        'total_links_count' => $this->collections->links($collection, FALSE)->count()
      ];

      if (!isset($collection['cover_asset']['fixed']) || $collection['cover_asset']['fixed'] == false)
      {
        foreach ($this->collections->links($collection, 10, null, null, ['lead_image' => 1]) as $link)
        {
          if ($link['lead_image'])
          {
            $data['cover_asset'] = $link['lead_image'];
            $data['cover_asset']['fixed'] = false;
            break;
          }
        }
      }

      if (collection('collections')->update(
        ['id' => $collection['id']],
        [
          '$set' => $data
        ]
      )) $i++;;
    }

    unset($data);
    unset($collections);

    return $i;
  }
}