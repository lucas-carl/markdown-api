<?php

use Api\Models\User;
use Api\Models\File;
use Api\Models\Folder;

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
  $user = User::where('token', $token)->where('token_expire', '>', date('Y-m-d H:i:s'))->first();

  if (!$token || !$user) {
    return $response->withStatus(401);
  }

  $user->token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));
  $user->save();

  return $next($request->withAttribute('user', $user->id), $response);
};

$app->get('/', function ($request, $response) {
  return "Markdown Journal API v2.0.0";
});

$app->get('/files', function ($request, $response) {
  $files = File::where('user_id', $request->getAttribute('user'))->get();

  if (!$files) {
    return $response->withStatus(404, 'Not found');
  }

  return $response->withJson($files);
})->add($authenticated);

$app->get('/files/all', function ($request, $response) {
  $files = File::withTrashed()->where('user_id', $request->getAttribute('user'))->get();

  if (!$files) {
    return $response->withStatus(404, 'Not found');
  }

  return $response->withJson($files);
})->add($authenticated);

$app->post('/files', function ($request, $response) {
  $data = $request->getParsedBody();
  $user = $request->getAttribute('user');

  $id = md5(uniqid($data['title'] . time(), true));
  $content = '## ' . $data['title'];

  $file = new File();
  $file->id = $id;
  $file->title = $data['title'];
  $file->content = $content;
  $file->user_id = $user;
  $file->save();

  return $response->withJson($file);
})->add($authenticated);

$app->get('/files/{id}', function ($request, $response, $args) {
  $file = File::where('id', $args['id'])->where('user_id', $request->getAttribute('user'))->first();

  if (!$file) {
    return $response->withStatus(404, 'Not found');
  }

  return $response->withJson($file);
})->add($authenticated);

$app->put('/files/{id}', function ($request, $response, $args) {
  $data = $request->getParsedBody();

  $file = File::where('id', $args['id'])->where('user_id', $request->getAttribute('user'))->first();

  if (isset($data['title'])) {
    $file->title = $data['title'];
  }

  if (isset($data['content'])) {
    $file->content = $data['content'];
  }

  if (isset($data['folder_id'])) {
    $file->folder_id = $data['folder_id'];
  }

  $file->save();

  return $response->withJson($file);
})->add($authenticated);

$app->delete('/files/{id}', function ($request, $response, $args) {
  File::where('id', $args['id'])->where('user_id', $request->getAttribute('user'))->delete();

  return $response->withStatus(204);
})->add($authenticated);

$app->post('/files/{id}/restore', function ($request, $response, $args) {
  File::find($args['id'])->restore();

  return $response->withStatus(204);
})->add($authenticated);

$app->delete('/files', function ($request, $response) {
  File::onlyTrashed()->where('user_id', $request->getAttribute('user'))->forceDelete();

  return $response->withStatus(204);
})->add($authenticated);

$app->post('/files/{id}/move', function ($request, $response, $args) {
  $data = $request->getParsedBody();

  $file = File::where('id', $args['id'])->where('user_id', $request->getAttribute('user'))->first();

  $file->folder_id = $data['destination'] == 'null' ? null : $data['destination'];
  $file->save();

  return $response->withStatus(204);
})->add($authenticated);

$app->get('/folders', function ($request, $response) {
  $folders = Folder::where('user_id', $request->getAttribute('user'))->get();

  if (!$folders) {
    return $response->withStatus(204);
  }

  return $response->withJson($folders);
})->add($authenticated);

$app->post('/folders', function ($request, $response) {
  $data = $request->getParsedBody();

  $id = md5(uniqid($data['title'] . time(), true));

  $folder = new Folder();
  $folder->id = $id;
  $folder->title = $data['title'];
  $folder->user_id = $request->getAttribute('user');
  $folder->save();

  return $response->withJson(['id' => $id]);
})->add($authenticated);

$app->delete('/folders/{id}', function ($request, $response, $args) {
  Folder::where('id', $args['id'])->where('user_id', $request->getAttribute('user'))->delete();

  File::where('folder_id', $args['id'])->where('user_id', $request->getAttribute('user'))->update(['folder_id' => null]);

  return $response->withStatus(204);
})->add($authenticated);

$app->post('/users/login', function ($request, $response) {
  $data = $request->getParsedBody();

  $user = User::where('email', $data['email'])->first();

  if ($user && password_verify($data['password'], $user->password)) {
    $token = bin2hex(openssl_random_pseudo_bytes(8));
    $token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));

    $user->token = $token;
    $user->token_expire = $token_expire;
    $user->save();

    return $response->withJson([ 'email' => $data['email'], 'token' => $token ]);
  }

  return $response->withStatus(401, 'Bad credentials');
});

$app->post('/users', function ($request, $response) {
  $data = $request->getParsedBody();

  if (User::where('email', $data['email'])->exists()) {
    return $response->withStatus(412, 'Email already in use');
  }

  $id = md5(uniqid($data['email'] . time(), true));
  $token = bin2hex(openssl_random_pseudo_bytes(8));
  $token_expire = date('Y-m-d H:i:s', strtotime('+8 hours'));

  $user = new User();
  $user->id = $id;
  $user->email = $data['email'];
  $user->password = password_hash($data['password'], PASSWORD_DEFAULT);
  $user->token = $token;
  $user->token_expire = $token_expire;
  $user->save();

  return $response->withJson([ 'email' => $data['email'], 'token' => $token ]);
});
