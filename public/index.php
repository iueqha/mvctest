<?php
//var_dump(111);die;
require '../init.php';
define('APP_PATH', ROOT_PATH . 'app/');
require_once '../library/Route.php';

$route = new Route();
$route->run();