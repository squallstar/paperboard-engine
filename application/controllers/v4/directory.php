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
  public function category_collections($slug)
  {
    $this->load->model('model_collections', 'collections');

    $this->json(200, iterator_to_array(
      $this->collections->find_by_category($slug)
    , false)
    );
  }
}