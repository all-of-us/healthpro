<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$allowSymfony = !($app->isStable() || $app->isProd());
$path = @parse_url($_SERVER['REQUEST_URI'])['path'];

if ($allowSymfony && preg_match("/^\/s(\/|$)/", $path)) {
    require '../symfony/public/index.php';
} else {
    require 'silex-index.php';
}
