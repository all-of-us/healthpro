<?php
use Pmi\Controller;

$app['templatesDirectory'] = realpath(__DIR__ . '/../views');
$app['webpackBuildDirectory'] = realpath(__DIR__ . '/../web/build');

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
    ->mount('/', new Controller\EvaluationController())
    ->mount('/', new Controller\SymfonyMigrationController())
    ->mount('/_dev', new Controller\DevController())
    ->mount('/cron', new Controller\CronController())
    ->mount('/_ah', new Controller\AppEngineController())
;

$app->run();
