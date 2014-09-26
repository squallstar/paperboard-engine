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
      [
        'id' => true,
        'user.id' => true,
        'feeds' => true,
        'sources' => true,
        'cover_asset.fixed' => true
      ]
    );

    $i = 0;

    foreach ($collections as $collection)
    {
      $data = [
        'total_links_count' => $this->collections->links_count($collection)
      ];

      $cursor = $this->collections->links_ordered($collection, 400, null, null, ['lead_image' => 1, 'entities' => 1])->sort(['published_at' => -1]);

      $needs_cover = !isset($collection['cover_asset']['fixed']) || $collection['cover_asset']['fixed'] == false;

      $entities = [];

      foreach ($cursor as $link)
      {
        if ($needs_cover)
        {
          if ($link['lead_image'])
          {
            $data['cover_asset'] = $link['lead_image'];
            $data['cover_asset']['fixed'] = false;
            $needs_cover = false;
          }
        }

        if (isset($link['entities']) && count($link['entities']))
        {
          foreach ($link['entities'] as &$entity)
          {
            if (isset($entities[$entity['text']]))
            {
              $entities[$entity['text']] += $entity['frequency'];
            }
            else
            {
              $entities[$entity['text']] = $entity['frequency'];
            }
          }

          unset($entity);
        }
      }

      if (count($entities))
      {
        $data['tags'] = [];

        arsort($entities);

        foreach ($entities as $text => $freq)
        {
          $data['tags'][] = $text;

          if (count($data['tags']) > 20) break;
        }
      }

      unset($needs_cover);
      unset($cursor);

      $user = collection('users')->findOne(['_id' => $collection['user']['id']], [
        'full_name' => 1, 'avatar.small' => 1
      ]);

      if ($user)
      {
        $data['user'] = [
          'id' => $user['_id'],
          'full_name' => $user['full_name'],
          'image_url' => $user['avatar']['small']
        ];

        if (collection('collections')->update(
          ['id' => $collection['id']],
          [
            '$set' => $data
          ]
        )) $i++;;
      }
      else
      {
        // Dead collection?
        collection('collections')->remove(['id' => $collection['id']], ['justOne' => true]);
      }
    }

    unset($data);
    unset($collections);

    return $i;
  }
}