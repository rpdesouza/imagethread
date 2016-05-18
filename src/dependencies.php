<?php
// DIC configuration

use \Model;

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], Monolog\Logger::DEBUG));
    return $logger;
};

// database
$container['db'] = function ($c) {
	$database = $c->get('settings')['database'];
	$db = new PDO("sqlite:{$database['path']}");
	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	return $db;
};

// flash messages
$container['flash'] = function () {
	return new \Slim\Flash\Messages();
};

// mapper
$container['mapper'] = function ($c) {
	$mapper = new Model\Mapper\Image;
	$mapper->setDb($c->get('db'));
	$mapper->setLogger($c->get('logger'));
	return $mapper;
};

// domain
$container['domain'] = function ($c) {
	return new Model\Domain\Image;
};