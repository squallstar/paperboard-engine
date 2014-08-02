<?php
/**
 * user model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

Class Model_collections extends CI_Model
{
  public function __construct()
  {
    parent::__construct();
  }

  private function _prepare($data = array())
  {
    $data = array_intersect_key($data, array(
      'name', 'position', 'publicly_visible', 'settings', 'filters')
    );

    return array_replace_recursive(array(
      'id' => newid(),
      'private_id' => newid('p'),
      'name' => 'New collection',
      'description' => '',
      'position' => 0,
      'owned_collection' => true,
      'publicly_visible' => true,
      'partner_identifier' => null,
      'type' => 'standard',
      'total_links_count' => 0,
      'average_rating' => 0.0,
      'total_ratings' => 0,
      'changed_since_last_updated' => false,
      'last_updated_at' => time(),
      'filters_updated_at' => time(),
      'settings' => array(
        'color' => 'green',
        'links' => array(
          'displayStyle' => 'headline+image'
        )
      ),
      'filters' => array(),
      'user' => array(
        'id' => $this->users->get('_id'),
        'full_name' => $this->users->get('full_name'),
        'image_url' => $this->users->get('avatar')['small']
      ),
      'sources' => array(
      )
    ), $data);
  }

  public function save($data = array())
  {
    $data = $this->_prepare($data);

    $res = collection('collections')->save($data, array('safe' => true));
    return $res ? $data : false;
  }
}