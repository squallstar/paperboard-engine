<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$route['default_controller'] = "welcome";
$route['404_override'] = '';

$route['v3/sign_in'] = 'v3/user/sign_in';
$route['v3/sign_up'] = 'v3/user/sign_up';

$route['v3/collections/reorder'] = 'v3/collections/reorder';
$route['v3/collections/follow'] = 'v3/collections/follow_many';
$route['v3/collections/(p?[A-z0-9]+)'] = 'v3/collections/view/$1';
$route['v3/collections/(p?[A-z0-9]+)/links'] = 'v3/collections/view_links/$1';
$route['v3/collections/(p?[A-z0-9]+)/publish'] = 'v3/collections/publish/$1';
$route['v3/collections/(p?[A-z0-9]+)/follow'] = 'v3/collections/follow/$1';
$route['v3/collections/(p?[A-z0-9]+)/source_management'] = 'v1/source_management/collection_nodes/$1';
$route['v3/search_collection/links'] = 'v3/collections/search_links';

$route['v3/articles/([A-z0-9\-]+)/suggested'] = 'v3/articles/suggested/$1';
$route['v3/articles/([A-z0-9\-]+)/siblings'] = 'v3/articles/siblings/$1';
$route['v3/articles/([A-z0-9\-]+)'] = 'v3/articles/view/$1';

$route['v3/favourite_collection'] = 'v3/collections/favourite_collection';
$route['v3/favourite_collection/links'] = 'v3/collections/favourite_collection_links';

$route['v4/directory/([A-z0-9]+)'] = 'v4/directory/category_collections/$1';

$route['v1/source_management/add_feed_category'] = 'v1/source_management/add_category';
$route['v1/source_management/([A-z0-9]+)/add_feed'] = 'v1/source_management/add_feed/$1';
$route['v1/source_management/([A-z0-9]+)/rename'] = 'v1/source_management/rename_category/$1';
$route['v1/source_management/([A-z0-9\-,]+)/move'] = 'v1/source_management/move_node/$1';

$route['v1/source_management/add_twitter_account'] = 'v1/source_management/add_twitter_account';
$route['v1/source_management/add_instagram_account'] = 'v1/source_management/add_instagram_account';
$route['v1/source_management/add_feedly_account'] = 'v1/source_management/add_feedly_account';
$route['v(1|3)/source_management/([A-z0-9,\-]+)'] = 'v1/source_management/node/$2';

$route['auth/twitter'] = 'v1/source_management/add_twitter_account';

$route['v1/avatar'] = 'v3/user/avatar';

/* End of file routes.php */
/* Location: ./application/config/routes.php */