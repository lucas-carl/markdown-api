<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/', function (Request $request, Response $response) {
  return "Markdown Journal API v1.0.0";
});

$app->get('/files', function (Request $request, Response $response) {
	$q = $this->db->prepare('SELECT * FROM files');
	$q->execute();
	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS));
});
