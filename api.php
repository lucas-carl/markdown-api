<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

$app->get('/', function (Request $request, Response $response) {
  return "Markdown Journal API v1.0.0";
});

$app->get('/files', function (Request $request, Response $response) {
	$q = $this->db->prepare('SELECT * FROM files WHERE not_deleted = 1');
	$q->execute();
	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS));
});

$app->post('/files', function (Request $request, Response $response) {
	$data = $request->getParsedBody();

	$q = $this->db->prepare('INSERT INTO files (id, title, user_id) VALUES (:id, :title, :user_id)');
	$q->execute([
		':id' => '1',
		':title' => $data['title'],
		':user_id' => $data['user_id']
	]);

	return $response->withJson($data);
});

$app->get('/files/{id}', function (Request $request, Response $response, $args) {
	$q = $this->db->prepare('SELECT * FROM files WHERE id = :id');
	$q->execute([
		':id' => $args['id']
	]);

	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS));
});

$app->delete('/files/{id}', function (Request $request, Response $response, $args) {
	$q = $this->db->prepare('UPDATE files SET deleted_at = :now, not_deleted = 0 WHERE id = :id');
	$q->execute([
		':id' => $args['id'],
		':now' => date('Y-m-d H:i:s')
	]);

	return $response->withStatus(204);
});

$app->post('/files/{id}/restore', function (Request $request, Response $response, $args) {
	$q = $this->db->prepare('UPDATE files SET deleted_at = null, not_deleted = 1 WHERE id = :id');
	$q->execute([
		':id' => $args['id']
	]);

	return $response->withStatus(204);
});
