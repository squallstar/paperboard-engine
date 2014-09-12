<?php

class Feedly
{
  private $_api_url;
  private $_api_client_id;
  private $_api_client_secret;

  private $_access_token;

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

  public function getLoginUrl()
  {
    $url = $this->url('/v3/auth/auth', [
      'response_type' => 'code',
      'client_id' => $this->_api_client_id,
      'redirect_uri' => $this->config->item('feedly_redirect_uri'),
      'scope' => 'https://cloud.feedly.com/subscriptions'
    ]);

    return $url;
  }

  public function setAccessToken($token)
  {
    $this->_access_token = $token;
  }

  public function getAccessToken($code)
  {
    $res = $this->_post('/v3/auth/token', [
      'code' => $code,
      'redirect_uri' => $this->config->item('feedly_redirect_uri'),
      'grant_type' => 'authorization_code'
    ]);

    return $res;
  }

  public function getProfile()
  {
    return $this->_get('/v3/profile');
  }

  public function getOpml()
  {
    ini_set("memory_limit", "256M");

    $res = $this->_get('/v3/opml', false);

    if ($res)
    {
      return simplexml_load_string($res);
    }
    else
    {
      return false;
    }
  }

  private function _get($url, $json = true)
  {
    return $this->_request('GET', $url, null, $json);
  }

  private function _post($url, $data = [], $client_data = true)
  {
    if ($client_data)
    {
      $data['client_id'] = $this->_api_client_id;
      $data['client_secret'] = $this->_api_client_secret;
    }

    return $this->_request('POST', $url, $data);
  }

  private function _request($type, $url, $postData = null, $json = true)
  {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $this->url($url));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    if ($type == 'POST')
    {
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }

    if ($this->_access_token)
    {
      curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: OAuth ' . $this->_access_token]);
    }

    $res = curl_exec($ch);

    if ($json) return json_decode($res);
    else return $res;
  }


}