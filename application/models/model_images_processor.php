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

  const DEFAULT_WIDTH_THUMBNAIL = 430;

  const DEFAULT_WIDTH_NORMAL = 640;

  const DEFAULT_JPEG_QUALITY = 83;

  private $_is_working;

  private $_bucket;

  public function __construct()
  {
    parent::__construct();

    $this->load->library('S3', [$this->config->item('aws_consumer_key'), $this->config->item('aws_consumer_secret')]);
    $this->_bucket = $this->config->item('aws_bucket_name');
  }

  public function delete_asset($asset)
  {
    foreach (['url_archived_small', 'url_archived_medium'] as $key)
    {
      $this->_delete_assets($asset, $key);
    }

    return true;
  }

  private function _delete_assets($asset, $key)
  {
    if (isset($asset[$key]) && strlen($asset[$key]))
    {
      if (strpos($asset[$key], $this->_bucket) !== FALSE)
      {
        $name = str_replace(self::AWS_URL . $this->_bucket . '/', '', $asset[$key]);

        try
        {
          return S3::deleteObject($this->_bucket, $name);
        }
        catch (Exception $e)
        {
          log_message('error', $e->getMessage() . ' ' . $name);
        }
      }
    }
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
        'lead_image.url_original' => true,
        'url_host' => true
      ]
    )->sort(['published_at' => -1])
     ->hint(['has_image' => 1, 'images_processed' => 1])
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
          if (strpos($article['lead_image']['url_original'], '//') === 0)
          {
            $article['lead_image']['url_original'] = 'http:' . $article['lead_image']['url_original'];
          }

          $s3path = 'articles/' . date('Ymd/') . $article['id'] . time();

          $url = $article['lead_image']['url_original'];

          if (strpos($url, '/') === 0)
          {
            $url = 'http://' . $article['url_host'] . $url;
          }

          // 1. Medium size
          $image->clear();
          @$image->readImage($url);
          $image->setFormat("jpeg");
          $image->setCompressionQuality(self::DEFAULT_JPEG_QUALITY);
          //$im_thumb = clone $im;
          $image->thumbnailImage(self::DEFAULT_WIDTH_NORMAL, 0);
          $medium_name = $s3path . '_m.jpg';

          $sizes = $image->getImageGeometry();

          $normal_uploaded = S3::putObject("$image", $this->_bucket, $medium_name, S3::ACL_PUBLIC_READ, array(), array('Content-Type' => 'image/jpeg'));

          // 2. Thumbnail
          $image->thumbnailImage(self::DEFAULT_WIDTH_THUMBNAIL, 0);
          $thumb_name = $s3path . '_s.jpg';

          $thumb_uploaded = S3::putObject("$image", $this->_bucket, $thumb_name, S3::ACL_PUBLIC_READ, array(), array('Content-Type' => 'image/jpeg'));

          if ($normal_uploaded || $thumb_uploaded)
          {
            $data['lead_image'] = [
              'url_original' => $article['lead_image']['url_original'],
              'url_archived_small' => $aws_url . $thumb_name,
              'url_archived_medium' => $aws_url . $medium_name,
              'width' => $sizes['width'],
              'height' => $sizes['height']
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