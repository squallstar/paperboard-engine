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