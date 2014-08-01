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
}