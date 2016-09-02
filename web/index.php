<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['errorTemplate'] = 'error.html.twig';
if ($app->isDev()) {
    $app['memcacheSession'] = true;
    $app['twigCacheHandler'] = 'memcache';
} else {
    $app['memcacheSession'] = true;
    $app['cacheDirectory'] = realpath(__DIR__ . '/../cache');
    $app['twigCacheHandler'] = 'file';
}

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->run()
;
