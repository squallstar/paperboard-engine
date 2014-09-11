<?php

class Feedly
{
  private $_api_url;
  private $_api_client_id;
  private $_api_client_secret;

  public function __construct()
  {
    $this->_api_url = rtrim($this->config->item('feedly_api_url'), '/') . '/';
    $this->_api_client_id = $this->config->item('feedly_client_id');
    $this->_api_client_secret = $this->config->item('feedly_client_secret');
  }

  function __get($key)
  {
    $CI =& get_instance();
    return $CI->$key;
  }

  public function url($uri, $params = [])
  {
    $url = $this->_api_url . ltrim($uri, '/');

    if (count($params))
    {
      $tmp = [];
      foreach ($params as $key => $value) {
        $tmp[] = $key . '=' . urlencode($value);
      }

      $url = $url . '?' . implode('&', $tmp);
    }

    return $url;
  }

  public function getLoginUrl($redirect_to)
  {
    $url = $this->url('/v3/auth/auth', [
      'response_type' => 'code',
      'client_id' => $this->_api_client_id,
      'redirect_uri' => $redirect_to,
      'scope' => $this->_api_url . '/subscriptions'
    ]);

    return $url;
  }

  public function getAccessToken($code)
  {

  }

  private function _post($url, $data)
  {

  }
}