<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['errorTemplate'] = 'error.html.twig';

// Session timeout after 7 minutes (or 24 hours in local dev)
$app['sessionTimeout'] = $app->isLocal() ? 3600 * 24 : 7 * 60;
// Display warning 2 minutes before timeout
$app['sessionWarning'] = 2 * 60;

if (true) { // for now, use twig memcache everywhere
    $app['sessionHandler'] = 'datastore';
    $app['twigCacheHandler'] = 'memcache';
} else {
    $app['sessionHandler'] = 'datastore';
    $app['cacheDirectory'] = realpath(__DIR__ . '/../cache');
    $app['twigCacheHandler'] = 'file';
}

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->mount('/', new Controller\OrderController())
    ->mount('/', new Controller\EvaluationController())
    ->mount('/workqueue', new Controller\WorkQueueController())
    ->mount('/_dev', new Controller\DevController())
    ->mount('/cron', new Controller\CronController())
    ->mount('/dashboard', new Controller\DashboardController())
    ->mount('/admin', new Controller\AdminController())
    ->run()
;
