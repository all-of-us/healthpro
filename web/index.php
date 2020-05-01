<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication();
$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['errorTemplate'] = 'error.html.twig';

$app['sessionTimeout'] = 30 * 60; // Session timeout after 30 minutes
$app['sessionWarning'] = 2 * 60; // Display warning 2 minutes before timeout

$app['twigCacheHandler'] = 'file';
$app['twigCacheDirectory'] = sys_get_temp_dir() . '/healthpro/twig';

if ($app->isLocal()) {
    $app['sessionTimeout'] = 3600 * 24; // Extend session time out in local environment
} else {
    $app['sessionHandler'] = 'datastore';
}

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->mount('/', new Controller\OrderController())
    ->mount('/', new Controller\EvaluationController())
    ->mount('/', new Controller\ProblemController())
    ->mount('/', new Controller\SurveyController())
    ->mount('/_dev', new Controller\DevController())
    ->mount('/cron', new Controller\CronController())
    ->mount('/dashboard', new Controller\DashboardController())
    ->mount('/admin', new Controller\AdminController())
    ->mount('/help', new Controller\HelpController())
    ->mount('/workqueue', new Controller\WorkQueueController())
    ->mount('/problem', new Controller\ProblemReportController())
    ->mount('/review', new Controller\ReviewController())
    ->mount('/biobank', new Controller\BiobankController())
    ->mount('/_ah', new Controller\AppEngineController())
;

$app->run();
