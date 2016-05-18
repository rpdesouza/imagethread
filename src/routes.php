<?php

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

// Web Routes

// main route
$app->get('/', function (Request $request, Response $response) {
	// log message
	$this->logger->info("'/' route");

	// CSRF protection
	$csrf['name'] = $request->getAttribute('csrf_name');
	$csrf['value'] = $request->getAttribute('csrf_value');

	// get the images
	$images = $this->mapper->fetchAll();

	// messages
	$messages = $this->flash->getMessages();

	// views
	$views = 0;

	// render the view
	return $this->renderer->render($response, 'index.phtml', ['csrf' => $csrf, 'images' => $images, 'views' => $views, 'messages' => $messages]);
});

// do the upload
$app->post('/', function (Request $request, Response $response) {

	// get the title
	$title = filter_var($request->getParam('title'), FILTER_SANITIZE_STRING, FILTER_FLAG_ENCODE_AMP);

	// upload and save the image
	if ($this->mapper->upload('image')) {
		$domain = new Model\Domain\Image(NULL, $title, $this->mapper->getFile()->getNameWithExtension());
		$this->mapper->save($domain);
	}

	// error messages
	if (NULL !== $this->mapper->getFile()->getErrors()) {
		foreach($this->mapper->getFile()->getErrors() as $message) {
			$this->flash->addMessage('errors', $message);
		}
	}

	return $response->withRedirect('/');
});

// export the csv
$app->get('/export', function (Request $request, Response $response) {

	// get the images
	$images = $this->mapper->fetchAll();
	$images = array_map(function($item) {
		return [
			'id' =>    $item->getId(),
			'title' => $item->getTitle(),
			'path' =>  $item->getPath(),
		];
	}, $images);

	$file_name = "images_export_" . date("Y-m-d") . ".csv";

	$this->logger->info("writing {$file_name}");

	// force download
	$now = gmdate("D, d M Y H:i:s");
	header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
	header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
	header("Last-Modified: {$now} GMT");
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment;filename={$file_name}");
	header("Content-Transfer-Encoding: binary");

	// creating the file
	ob_start();
	$df = fopen("php://output", 'w');
	fputcsv($df, array_keys(reset($images)));
	foreach ($images as $row) {
		fputcsv($df, $row);
	}
	fclose($df);

	$this->logger->info("finish writing {$file_name}");

	return ob_get_clean();
});


// API Routes

// get one or all images
$app->get('/api/images[/{id}]', function (Request $request, Response $response, $args) {
	// log message
	$this->logger->info("'/api/images' route");

	if (isset($args['id']) && filter_var($args['id'], FILTER_VALIDATE_INT) !== false) {
		$images[] = $this->mapper->find($args['id']);
	} else {
		$images = $this->mapper->fetchAll();
	}

	// get the images
	$images = $images = array_map(function($item) {
		return [
			'id' =>    $item->getId(),
			'title' => $item->getTitle(),
			'path' =>  $item->getPath(),
		];
	},$images);

	$response->withHeader('Content-Type', 'application/json');
	$response->write(json_encode($images));
	return $response;
});

// get the numbers of images
$app->get('/api/posts', function (Request $request, Response $response) {
	// log message
	$this->logger->info("'/api/posts' route");

	// get the images
	$images = $this->mapper->fetchAll();

	$response->withHeader('Content-Type', 'application/json');
	$response->write(json_encode(['total' => count($images)]));
	return $response;
});