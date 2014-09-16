<?php
/**
 * tags model
 *
 * @package     Paperboard
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_tags extends CI_Model
{
  public function popular($limit = 30, $filter = [])
  {
    $pipeline = [
      ['$unwind' => '$tags'],
      ['$group' => [
        '_id' => '$tags',
        'value' => ['$sum' => 1],
        'collections' => ['$push' => '$id']
      ]],
      [
        '$match' => [
          '_id' => [
            '$nin' => $filter
          ]
        ]
      ],
      ['$sort' => ['value' => -1]],
      ['$limit' => $limit]
    ];

    $res = collection('collections')->aggregate($pipeline)['result'];

    foreach ($res as $tag)
    {
      $tags[] = [
        'name' => $tag['_id']
      ];
    }

    return $tags;
  }

  public function suggested($limit = 15, $tags = [])
  {
    $pipeline = [
      [
        '$match' => [
          'tags' => [
            '$in' => $tags
          ]
        ]
      ],
      [
        '$unwind' => '$tags'
      ],
      [
        '$group' => [
          '_id' => '$tags',
          'value' => ['$sum' => 1]
        ]
      ],
      [
        '$match' => [
          '_id' => [
            '$nin' => $tags
          ]
        ]
      ],
      [
        '$sort' => ['value' => -1]
      ],
      [
        '$limit' => $limit
      ]
    ];

    $res = collection('collections')->aggregate($pipeline)['result'];

    $data = [];

    foreach ($res as $tag)
    {
      $data[] = [
        'name' => $tag['_id'],
        'suggested' => true
      ];
    }

    return $data;
  }
}