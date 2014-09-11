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
  public function recap()
  {
    if (!$this->require_token()) return;

    $this->load->model('model_collections', 'collections');

    $this->json(200, [
      'collections' => $this->collections->find_mine(),
      'user' => $this->users->find($this->users->get('_id'))
    ]);
  }

  /**
   * Finds a collection that the user is not following
   *
   */
  public function one_for_walkthrough()
  {
    if (!$this->require_token()) return;

    $fields = ['_id' => 0, 'id' => 1, 'private_id' => 1];

    $user_id = $this->users->get('_id');

    $collection = collection('collections')->findOne(
      [
        'user.id' => [
          '$ne' => $user_id
        ],
        'followers.id' => [
          '$ne' => $user_id
        ],
        'total_links_count' => [
          '$gt' => 500
        ]
      ],
      $fields
    );

    if (!$collection)
    {
      // Fallback. Should never go here though
      $collection = collection('collections')->findOne([], $fields);
    }

    $this->json(200, [
      'collection' => $collection
    ]);
  }

  public function auto_complete()
  {
    $q = $this->input->get('q');

    $this->json(200, array(
      'results' => [
        [
          'type' => 'keywords',
          'text' => $q,
          'filter_value' => $q,
          'text_highlights' => []
        ],
        [
          'type' => 'phrase',
          'text' => $q,
          'filter_value' => $q,
          'text_highlights' => []
        ]
      ]
    ));
  }
}