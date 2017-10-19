<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: token');
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');

$app->add(function ($request, $response, $next) {
	if ($request->isOptions()) {
		return $next($requet, $response);
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
});

$app->get('/', function ($request, $response) {
  return "Markdown Journal API v1.0.0";
});

$app->get('/files', function ($request, $response) {
	$q = $this->db->prepare('SELECT * FROM files WHERE user_id = :user AND not_deleted = 1');
	$q->execute([
		':user' => $request->getAttribute('user')
	]);

	$files = $q->fetchAll(PDO::FETCH_CLASS);

	if (! $files) {
		return $response->withStatus(404, 'Not found');
	}

	return $response->withJson($files);
});

$app->post('/files', function ($request, $response) {
	$id = md5(uniqid($request->getAttribute('title') . $request->getAttribute('user'), true));

	$i = $this->db->prepare('INSERT INTO files (id, title, user_id, content) VALUES (:id, :title, :user, "")');
	$i->execute([
		':id' => $id,
		':title' => $request->getAttribute('title'),
		':user' => $request->getAttribute('user')
	]);

	$q = $this->db->prepare('SELECT * FROM files WHERE id = :id');
	$q->execute([
		':id' => $id
	]);

	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS)[0]);
});

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
});

$app->put('/files/{id}', function ($request, $response, $args) {
	$u = $this->db->prepare('UPDATE files SET title = :title, content = :content, folder_id = :folder_id WHERE user_id = :user AND id = :id');
	$u->execute([
		':id' => $args['id'],
		':title' => $request->getAttribute('title'),
		':content' => $request->getAttribute('content'),
		':folder_id' => $request->getAttribute('folder_id'),
		':user' => $request->getAttribute('user')
	]);

	$q = $this->db->prepare('SELECT * FROM files WHERE id = :id');
	$q->execute([
		':id' => $args['id']
	]);

	return $response->withJson($q->fetchAll(PDO::FETCH_CLASS)[0]);
});

$app->delete('/files/{id}', function ($request, $response, $args) {
	$u = $this->db->prepare('UPDATE files SET deleted_at = :now, not_deleted = 0 WHERE user_id = :user AND id = :id');
	$u->execute([
		':id' => $args['id'],
		':now' => date('Y-m-d H:i:s'),
		':user' => $request->getAttribute('user')
	]);

	return $response->withStatus(204);
});

$app->post('/files/{id}/restore', function ($request, $response, $args) {
	$u = $this->db->prepare('UPDATE files SET deleted_at = null, not_deleted = 1 WHERE id = :id');
	$u->execute([
		':id' => $args['id']
	]);

	return $response->withStatus(204);
});

$app->post('/user/login', function ($request, $response) {
	$email = $request->getAttribute('email');

	$q = $this->db->prepare('SELECT * FROM users WHERE email = :email');
	$q->execute([
		':email' => $email
	]);

	$user = $q->fetchAll(PDO::FETCH_CLASS)[0];

	if ($user && password_verify($request->getAttribute('password'), $user->password)) {
		$token = bin2hex(openssl_random_pseudo_bytes(8));

		$token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));

		$u = $this->db->prepare('UPDATE users SET token = :token, token_expire = :token_expire WHERE email = :email');
		$u->execute([
			':email' => $email,
			':token' => $token,
			':token_expire' => $token_expire
		]);

		return $response->withJson([ 'email' => $email, 'token' => $token ]);
	}

	return $response->withStatus(401, 'Bad credentials');
});

$app->post('/user', function ($request, $response) {
	$i = $this->db->prepare('INSERT INTO users (email, password) VALUES (:email, :password)');
	$i->execute([
		':email' => $request->getAttribute('email'),
		':password' => password_hash($request->getAttribute('password'), PASSWORD_DEFAULT)
	]);

	return $response->withStatus(204);
});
