<?php
require_once __DIR__ . '/../vendor/autoload.php';
$app = new Pmi\Application\HpoApplication();

$app->get('/', function() {
    return 'Hello, world';
});

$app->run();
