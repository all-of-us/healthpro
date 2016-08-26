<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Pmi\Controller;
use Pmi\Application\HpoApplication;

$app = new HpoApplication([
    'templatesDirectory' => __DIR__ . '/../views',
    'errorTemplate' => 'error.html.twig'
]);

$app
    ->setup()
    ->mount('/', new Controller\DefaultController())
    ->run()
;
