<?php

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: token');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');

$authenticated = function (Request $request, Response $response, $next) {
	if ($request->isOptions()) {
		return $next($request, $response);
	}

	$token = $request->getHeaderLine('Token');

	$q = $this->db->prepare('SELECT email, token FROM users WHERE token = :token AND token_expire > :now');
	$q->execute([
		':token' => $token,
		':now' => date('Y-m-d H:i:s')
	]);

	$user = $q->fetchAll(PDO::FETCH_CLASS)[0];

	if (!$token || !$user) {
		return $response->withStatus(401);
	}

	$token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));

	$u = $this->db->prepare('UPDATE users SET token_expire = :token_expire WHERE email = :email');
	$u->execute([
		':email' => $user->email,
		':token_expire' => $token_expire
	]);

	return $next($request->withAttribute('user', $user->email), $response);
};

$app->get('/', function ($request, $response) {
  return "Markdown Journal API v1.0.0";
});

$app->get('/files', function ($request, $response) {
	$q = $this->db->prepare('SELECT * FROM files WHERE user_id = :user AND not_deleted = 1');
	$q->execute([
		':user' => $request->getAttribute('user')
	]);

	$files = $q->fetchAll(PDO::FETCH_CLASS);

	if (!$files) {
		return $response->withStatus(404, 'Not found');
	}

	return $response->withJson($files);
})->add($authenticated);

$app->get('/files/all', function ($request, $response) {
	$q = $this->db->prepare('SELECT * FROM files WHERE user_id = :user');
	$q->execute([
		':user' => $request->getAttribute('user')
	]);

	$files = $q->fetchAll(PDO::FETCH_CLASS);

	if (!$files) {
		return $response->withStatus(404, 'Not found');
	}

	return $response->withJson($files);
})->add($authenticated);

$app->post('/files', function ($request, $response) {
	$data = $request->getParsedBody();

	$id = md5(uniqid($data['title'] . $request->getAttribute('user'), true));

	$i = $this->db->prepare('INSERT INTO files (id, title, user_id, content) VALUES (:id, :title, :user, "")');
	$i->execute([
		':id' => $id,
		':title' => $data['title'],
		':user' => $request->getAttribute('user')
	]);

	$q = $this->db->prepare('SELECT * FROM files WHERE id = :id');
	$q->execute([
		':id' => $id
	]);

	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS)[0]);
})->add($authenticated);

$app->get('/files/{id}', function ($request, $response, $args) {
	$q = $this->db->prepare('SELECT * FROM files WHERE user_id = :user AND id = :id');
	$q->execute([
		':id' => $args['id'],
		':user' => $request->getAttribute('user')
	]);

	$file = $q->fetchAll(PDO::FETCH_CLASS)[0];

	if (!$file) {
		return $response->withStatus(404, 'Not found');
	}

	return $response->withJson($file);
})->add($authenticated);

$app->put('/files/{id}', function ($request, $response, $args) {
	$data = $request->getParsedBody();

	$u = $this->db->prepare('UPDATE files SET title = :title, content = :content, folder_id = :folder_id WHERE user_id = :user AND id = :id');
	$u->execute([
		':id' => $args['id'],
		':title' => $data['title'],
		':content' => $data['content'],
		':folder_id' => $data['folder_id'],
		':user' => $request->getAttribute('user')
	]);

	$q = $this->db->prepare('SELECT * FROM files WHERE id = :id');
	$q->execute([
		':id' => $args['id']
	]);

	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS)[0]);
})->add($authenticated);

$app->delete('/files/{id}', function ($request, $response, $args) {
	$u = $this->db->prepare('UPDATE files SET deleted_at = :now, not_deleted = 0 WHERE user_id = :user AND id = :id');
	$u->execute([
		':id' => $args['id'],
		':now' => date('Y-m-d H:i:s'),
		':user' => $request->getAttribute('user')
	]);

	return $response->withStatus(204);
})->add($authenticated);

$app->post('/files/{id}/restore', function ($request, $response, $args) {
	$u = $this->db->prepare('UPDATE files SET deleted_at = null, not_deleted = 1 WHERE id = :id');
	$u->execute([
		':id' => $args['id']
	]);

	return $response->withStatus(204);
})->add($authenticated);

$app->delete('/files', function ($request, $response, $args) {
	$u = $this->db->prepare('DELETE FROM files WHERE user_id = :user AND not_deleted = 0');
	$u->execute([
		':user' => $request->getAttribute('user')
	]);

	return $response->withStatus(204);
})->add($authenticated);

$app->post('/user/login', function ($request, $response) {
	$data = $request->getParsedBody();

	$q = $this->db->prepare('SELECT * FROM users WHERE email = :email');
	$q->execute([
		':email' => $data['email']
	]);

	$user = $q->fetchAll(PDO::FETCH_CLASS)[0];

	if ($user && password_verify($data['password'], $user->password)) {
		$token = bin2hex(openssl_random_pseudo_bytes(8));

		$token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));

		$u = $this->db->prepare('UPDATE users SET token = :token, token_expire = :token_expire WHERE email = :email');
		$u->execute([
			':email' => $data['email'],
			':token' => $token,
			':token_expire' => $token_expire
		]);

		return $response->withJson([ 'email' => $data['email'], 'token' => $token ]);
	}

	return $response->withStatus(401, 'Bad credentials');
});

$app->post('/user', function ($request, $response) {
	$data = $request->getParsedBody();

	$q = $this->db->prepare('SELECT * FROM users WHERE email = :email');
	$q->execute([
		':email' => $data['email']
	]);

	$user = $q->fetchAll(PDO::FETCH_CLASS)[0];

	if ($user) {
		return $response->withStatus(412, 'Email already in use');
	}

	$i = $this->db->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
	$i->execute([
		':email' => $data['email'],
		':password' => $data['password']
	]);

	return $response->withStatus(204);
});
