<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['errorTemplate'] = 'error.html.twig';
if ($app->isProd() || $app->isDev()) { // for now, use twig memcache in prod
    $app['memcacheSession'] = true;
    $app['twigCacheHandler'] = 'memcache';
} else {
    $app['memcacheSession'] = true;
    $app['cacheDirectory'] = realpath(__DIR__ . '/../cache');
    $app['twigCacheHandler'] = 'file';
}

// currently used for POC for authenticating against Google Apps domain
$app['gaDomain'] = null;

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->mount('/', new Controller\OrderController())
    ->mount('/_dev', new Controller\DevController())
    ->mount('/googleapps', new Controller\GoogleAppsController())
    ->mount('/dashboard', new Controller\DashboardController())
    ->run()
;
