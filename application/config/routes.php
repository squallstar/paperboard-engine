<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$route['default_controller'] = "welcome";
$route['404_override'] = '';

$route['v3/sign_in'] = 'v3/user/sign_in';
$route['v3/sign_up'] = 'v3/user/sign_up';

$route['v3/collections/reorder'] = 'v3/collections/reorder';
$route['v3/collections/(p?[A-z0-9]+)'] = 'v3/collections/view/$1';
$route['v3/collections/(p?[A-z0-9]+)/links'] = 'v3/collections/view_links/$1';
$route['v3/collections/(p?[A-z0-9]+)/source_management'] = 'v1/source_management/collection_nodes/$1';
$route['v3/search_collection/links'] = 'v3/collections/search_links';

$route['v3/favourite_collection/links'] = 'v3/collections/favourite_collection_links';

$route['v1/source_management/add_feed_category'] = 'v1/source_management/add_category';
$route['v1/source_management/([A-z0-9]+)/add_feed'] = 'v1/source_management/add_feed/$1';
$route['v1/source_management/([A-z0-9]+)/rename'] = 'v1/source_management/rename_category/$1';
$route['v1/source_management/([A-z0-9]+)'] = 'v1/source_management/node/$1';

/* End of file routes.php */
/* Location: ./application/config/routes.php */