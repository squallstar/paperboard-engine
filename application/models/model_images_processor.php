<?php
/**
 * article downloader model
 *
 * @package     Cronycle
 * @author      Nicholas Valbusa - info@squallstar.it - @squallstar
 * @copyright   Copyright (c) 2014, Squallstar
 * @license     GNU/GPL (General Public License)
 * @link        http://squallstar.it
 *
 */

class Model_images_processor extends CI_Model
{
  private $_is_working;

  private $_bucket;

  private $_aws_url = 'https://s3-eu-west-1.amazonaws.com/';

  public function __construct()
  {
    parent::__construct();

    $this->load->library('S3', [$this->config->item('aws_consumer_key'), $this->config->item('aws_consumer_secret')]);
    $this->_bucket = $this->config->item('aws_bucket_name');
  }

  public function process($limit = 30)
  {
    if ($this->_is_working) return FALSE;

    $articles = iterator_to_array(
      collection('articles')->find(
        [
          'fetched_at' => [
            '$gt' => 0
          ],
          'images_processed' => false
        ],
        [
          '_id' => true,
          'lead_image.url_original' => true
        ]
      )->sort(['processed_at' => -1])
       ->limit($limit)
    , false);

    $n = count($articles);

    if ($n)
    {
      $this->_is_working = true;

      $count = $this->_process($articles);

      $this->_is_working = false;

      _log("Processed " . $count . " images");
    }

    unset($articles);

    return $n;
  }

  private function _process(&$articles)
  {
    $aws_url = $this->_aws_url . $this->_bucket . '/';

    $image = new Imagick();

    foreach ($articles as &$article)
    {
      $data = [
        'images_processed' => true
      ];

      if ($article['lead_image'])
      {
        try
        {
          $image->clear();
          @$image->readImage($article['lead_image']['url_original']);
          $image->setFormat("jpeg");
          $image->setCompressionQuality(85);
          $image->thumbnailImage(500, 0);

          $name = 'articles/' . date('Ymd/') . $article['_id']->{'$id'} . time() . '_s.jpg';

          $res = S3::putObject("$image", $this->_bucket, $name, S3::ACL_PUBLIC_READ, array(), array('Content-Type' => 'image/jpeg'));

          if ($res)
          {
            $d = $image->getImageGeometry();

            $data['lead_image'] = [
              'url_original' => $article['lead_image']['url_original'],
              'url_archived_small' => $aws_url . $name,
              'url_archived_medium' => $aws_url . $name,
              'width' => $d['width'],
              'height' => $d['height']
            ];

            unset($d);
          }
        }
        catch (Exception $e)
        {
          _log('Could not download image for article ' . $article['_id']->{'$id'});
        }
      }

      collection('articles')->update([
        '_id' => $article['_id']
      ],
      [
        '$set' => $data
      ]);
    }

    unset($name);
    unset($res);
    unset($article);
    unset($image);
    unset($aws_url);

    return count($articles);
  }

}