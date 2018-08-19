<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Api\Models\User;

require 'vendor/autoload.php';
require 'env.php';

if (ENV === 'development') {
  error_reporting(E_ALL);
  ini_set('display_errors', 'on');
}

$capsule = new Illuminate\Database\Capsule\Manager;

$capsule->addConnection([
  'driver'    => 'mysql',
  'host'      => DB_HOST,
  'port'      => DB_PORT,
  'database'  => DB_NAME,
  'username'  => DB_USER,
  'password'  => DB_PASSWORD,
  'charset'   => 'utf8',
  'collation' => 'utf8_unicode_ci',
]);

$capsule->bootEloquent();
$capsule->setAsGlobal();

$app = new \Slim\App([
  'settings' => [
    'displayErrorDetails' => true
  ]
]);

$app->get('/create', function () {
  $user = new User();
  $user->email = 't@test.de';
  $user->save();

  return $user;
});

require 'routes.php';

$app->run();
