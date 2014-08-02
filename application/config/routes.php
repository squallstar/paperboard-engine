<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$route['default_controller'] = "welcome";
$route['404_override'] = '';

$route['v3/sign_in'] = 'v3/user/sign_in';
$route['v3/sign_up'] = 'v3/user/sign_up';

$route['v3/collections/(p?[A-z0-9]+)'] = 'v3/collections/view';
$route['v3/collections/(p?[A-z0-9]+)/links'] = 'v3/collections/view_links';
$route['v3/favourite_collection/links'] = 'v3/collections/favourite_collection_links';

/* End of file routes.php */
/* Location: ./application/config/routes.php */