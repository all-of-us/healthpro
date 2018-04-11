<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['errorTemplate'] = 'error.html.twig';

// Session timeout after 30 minutes (or 24 hours in local dev)
$app['sessionTimeout'] = $app->isLocal() ? 3600 * 24 : 30 * 60;
// Display warning 2 minutes before timeout
$app['sessionWarning'] = 2 * 60;
$app['sessionHandler'] = 'datastore';

if ($app->isLocal()) {
    $app['twigCacheHandler'] = 'memcache';
} else {
    $app['cacheDirectory'] = realpath(__DIR__ . '/../cache');
    $app['twigCacheHandler'] = 'file';
}

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->mount('/', new Controller\OrderController())
    ->mount('/', new Controller\EvaluationController())
    ->mount('/', new Controller\ProblemController())
    ->mount('/_dev', new Controller\DevController())
    ->mount('/cron', new Controller\CronController())
    ->mount('/dashboard', new Controller\DashboardController())
    ->mount('/admin', new Controller\AdminController())
    ->mount('/help', new Controller\HelpController())
    ->mount('/workqueue', new Controller\WorkQueueController())
    ->mount('/problem', new Controller\ProblemReportController())
    ->mount('/today', new Controller\TodayController())
;

$app->run();
