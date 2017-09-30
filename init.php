<?php
define('ROOT_PATH', str_replace('\\','/',realpath(__DIR__)) . '/');
define('APP_PATH', ROOT_PATH . 'app/');

$dbConfig = array(
    'adapter' => 'Pdo_Mysql',
    'server' => 'localhost',
    'port' => 3306,
    'username' => 'root',
    'password' => 'root',
    'database' => 'test_data',
    'charset' => 'utf8',
    'persitent' => false
);

spl_autoload_register(function ($class) {
    $file = str_replace('\\', '/', $class).'.php';

    if (is_file(ROOT_PATH . $file)) {
        require_once ROOT_PATH . $file;
    } elseif (is_file(ROOT_PATH . 'library/' . $file)) {
        require_once ROOT_PATH . 'library/' . $file;
    } elseif (is_file(APP_PATH.'model/'.$file)) {
        require_once APP_PATH.'model/'.$file;
    }
});


