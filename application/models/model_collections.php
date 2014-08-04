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
    $data = array_intersect_key(
      $data,
      array(
        'name' => true,
        'position' => true,
        'publicly_visible' => true,
        'settings' => true,
        'filters' => true,
        'sources' => true
      )
    );

    return array_replace_recursive(array(
      'id' => next_id('collection'),
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

    $res = collection('collections')->save($data);

    if ($res)
    {
      unset($data['_id']);
      return $data;
    }

    return false;
  }

  public function update($collection_id, $data = array())
  {
    $q = $this->_where_id($collection_id);

    return collection('collections')->update($q, array('$set' => $data));
  }

  public function find($collection_id, $fields = array())
  {
    $q = $this->_where_id($collection_id);
    return collection('collections')->findOne($q, $fields);
  }

  private function _where_id($collection_id)
  {
    if (strpos($collection_id, 'p') === 0)
    {
      return array('private_id' => $collection_id);
    } else {
      return array(
        'id' => intval($collection_id),
        'user.id' => $this->users->get('_id')
      );
    }
  }
}