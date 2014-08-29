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
  const AWS_URL = 'https://s3-eu-west-1.amazonaws.com/';

  const DEFAULT_WIDTH = 400;

  const DEFAULT_JPEG_QUALITY = 83;

  private $_is_working;

  private $_bucket;

  public function __construct()
  {
    parent::__construct();

    $this->load->library('S3', [$this->config->item('aws_consumer_key'), $this->config->item('aws_consumer_secret')]);
    $this->_bucket = $this->config->item('aws_bucket_name');
  }

  public function upload_image($folder, $filename, $width = 'auto', $height = 0)
  {
    $image = false;

    if ($width != 'auto')
    {
      // Resample image
      $image = new Imagick();

      $image->readImage($filename);
      $image->setFormat("jpeg");
      $image->setCompressionQuality(80);
      $image->thumbnailImage($width, $height);
    }
    else
    {
      $image = file_get_contents($filename);
    }

    $name = $folder . '/' . md5($filename . $width . $height) . time() . '.jpg';

    $res = S3::putObject("$image", $this->_bucket, $name, S3::ACL_PUBLIC_READ, array(), array('Content-Type' => 'image/jpeg'));

    if ($res)
    {
      return self::AWS_URL . $this->_bucket . '/' . $name;
    }
    else
    {
      return false;
    }
  }

  public function process($limit = 30)
  {
    if ($this->_is_working) return FALSE;

    $articles = collection('articles')->find(
      [
        'has_image' => true,
        'images_processed' => false
      ],
      [
        'id' => true,
        'lead_image.url_original' => true
      ]
    )->sort(['published_at' => -1])
     ->limit($limit);

    $this->_is_working = true;
    $count = $this->_process($articles);
    $this->_is_working = false;

    _log("Processed " . $count . " images");

    unset($articles);

    return $count;
  }

  private function _process($articles)
  {
    $aws_url = self::AWS_URL . $this->_bucket . '/';

    $image = new Imagick();

    $i = 0;

    foreach ($articles as $article)
    {
      $data = [
        'images_processed' => true
      ];

      if ($article['lead_image'] && isset($article['lead_image']['url_original']))
      {
        try
        {
          $image->clear();
          @$image->readImage($article['lead_image']['url_original']);
          $image->setFormat("jpeg");
          $image->setCompressionQuality(self::DEFAULT_JPEG_QUALITY);
          $image->thumbnailImage(self::DEFAULT_WIDTH, 0);

          $name = 'articles/' . date('Ymd/') . $article['id'] . time() . '_s.jpg';

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
          _log('Could not download image for article ' . $article['id'] . ': ' . $article['lead_image']['url_original']);
          //$data['lead_image'] = null;
        }
      }

      collection('articles')->update([
        'id' => $article['id']
      ],
      [
        '$set' => $data
      ],
      [
        'w' => 0
      ]);

      $i++;
    }

    unset($name);
    unset($res);
    unset($image);
    unset($aws_url);

    return $i;
  }

}