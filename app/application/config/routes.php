<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
| 	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are two reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['scaffolding_trigger'] = 'scaffolding';
|
| This route lets you set a "secret" word that will trigger the
| scaffolding feature for added security. Note: Scaffolding must be
| enabled in the controller in which you intend to use it.   The reserved
| routes must come before any wildcard or regular expression routes.
|
*/

$route['(content|albums|text)/featured(/(:any))?'] = "features/index/model:$1$2";
$route['features/(content|album|text)s?(/(:any))?'] = "features/index/model:$1$2";
$route['albums/([\d\,]+)/(categories|topics)(/.*)?'] = "albums/$2/$1$3";
$route['albums/((?:[0-9]+)|(?:slug:[^/]+)|[0-9a-z]{32})/(content|covers)(/(.*))?'] = "albums/$2/$1/$3";
$route['albums/tree(:any)?'] = "albums/tree$1";
$route['albums/(:any)'] = "albums/index/$1";
$route['content/cache/?'] = "contents/cache";
$route['content/cache/(:any)'] = "contents/cache/$1";
$route['content/([\d\,]+)/(albums|categories)(/.*)?'] = "contents/$2/$1$3";
$route['content/(unlisted|private)(/(.*))?'] = "contents/index/visibility:$1$2";
$route['content(/(.*))?'] = "contents/index/$1";
$route['categories/((?:[0-9,]+)|(?:slug:[^/]+))/(content|albums|essays)(/(.*))?'] = "categories/members/$1/$2:true/$3";
$route['categories(/(.*))?'] = "categories/index/$1";
$route['history/?'] = "histories";
$route['history/(:any)'] = "histories/$1";
$route['users/([0-9]+)/content(/(.*))?'] = "users/content/$1/$2";
$route['users/reset_password/?$'] = "users/reset_password";
$route['users/reset_password/(.*)$'] = "users/reset_password/$1";
$route['users/verify_password/?$'] = "users/verify_password";
$route['users/(:any)'] = "users/index/$1";
$route['system/clear_caches/?'] = "system/clear_caches";
$route['system/(:any)'] = "system/index/$1";
$route['favorites/(:any)'] = "favorites/index/$1";
$route['favorites/(:any)'] = "favorites/index/$1";
$route['trash/?'] = "trashes";
$route['trash/(:any)'] = "trashes/index/$1";
$route['tags/(:any)?'] = "tags/index/$1";
$route['archives/(:any)?'] = "archives/index/$1";
$route['auth/token/(:any)'] = "auth/token/$1";
$route['auth/grant/?'] = "auth/grant";
$route['auth/(:any)'] = "auth/index/$1";
$route['sessions/(:any)'] = "sessions/index/$1";
$route['site/set_order'] = "sites/set_order";
$route['site/publish/([0-9]+)'] = "sites/publish/$1";
$route['site/?'] = "sites/index";
$route['site/(:any)'] = "sites/index/$1";
$route['themes/?'] = "themes/index";
$route['themes/(:any)'] = "themes/index/$1";
$route['drafts/?'] = "drafts/index";
$route['drafts/(:any)'] = "drafts/index/$1";
$route['text/?'] = "texts/index";
$route['text/oembed_preview/?'] = "texts/oembed_preview";
$route['text/([0-9]+)/(topics|categories)(/.*)?'] = "texts/$2/$1/$3";
$route['text/([0-9]+)/feature/?'] = "texts/feature/$1";
$route['text/([0-9]+)/feature/(:any)?'] = "texts/feature/$1/$2";
$route['text/drafts/?'] = "texts/drafts";
$route['text/drafts/(:any)?'] = "texts/drafts/$1";
$route['text/(:any)'] = "texts/index/$1";
$route['settings/test_email/?'] = "settings/test_email";
$route['settings/(:any)'] = "settings/index/$1";
$route['fixtures/?'] = "fixtures/index";
$route['update/plugin/?'] = "update/plugin";
$route['update/migrate/(:any)?'] = "update/migrate/$1";
$route['update/(:any)?'] = "update/index/$1";
$route['events/(\d{4}\-\d{1,2}\-\d{1,2})/?(:any)?'] = "events/show/$1/$2";
$route['events/(:any)?'] = "events/index/$1";
$route['plugins/compile/(:any)'] = "plugins/compile/$1";
$route['plugins/call/(:any)'] = "plugins/call/$1";
$route['plugins/(js|css)(:any)?/?'] = "plugins/$1/$2";
$route['plugins/(:any)?'] = "plugins/index/$1";

/* End of file routes.php */
/* Location: ./system/application/config/routes.php */
