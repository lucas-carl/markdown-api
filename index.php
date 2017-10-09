<?php
require 'vendor/autoload.php';

$config = [
  'settings' => [
    'displayErrorDetails' => true
  ]
];

$app = new \Slim\App($config);

$container = $app->getContainer();
$container['db'] = function () {
	$pdo = new PDO('sqlite:database.db') or die ('Failed to open DB.');
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
};

require 'api.php';

$app->run();
