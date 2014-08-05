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

  private function _default_excluded_fields()
  {
    return array('_id' => false, 'sources' => false);
  }

  private function _filter_fields($data = array())
  {
    return array_intersect_key(
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
  }

  private function _prepare($data = array())
  {
    $data = $this->_filter_fields($data);

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

  public function update($collection_id, $data = array(), $return = false)
  {
    $q = $this->_where_id($collection_id);
    $data = $this->_filter_fields($data);

    if ($return)
    {
      return collection('collections')->findAndModify(
        $q,
        array('$set' => $data),
        $this->_default_excluded_fields(),
        array('new' => true)
      );
    }
    else
    {
      return collection('collections')->update($q, array('$set' => $data));
    }
  }

  public function delete($collection_id)
  {
    return collection('collections')->remove(
      array(
        'id' => $collection_id
      ),
      array(
        'justOne' => true
      )
    ) ? true : false;
  }

  public function find($collection_id, $fields = array())
  {
    $q = $this->_where_id($collection_id);
    return collection('collections')->findOne($q, $fields);
  }

  public function find_mine()
  {
    return iterator_to_array(
      collection('collections')->find(
        array(
          'user.id' => $this->users->get('_id')
        ),
        $this->collections->_default_excluded_fields()
      ),
      false
    );
  }

  private function _where_id($collection_id)
  {
    if (strpos($collection_id, 'p') === 0)
    {
      return array('private_id' => $collection_id);
    } else {
      $this->users->load_user();
      return array(
        'id' => intval($collection_id),
        'user.id' => $this->users->get('_id')
      );
    }
  }
}