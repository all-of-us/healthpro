<?php
/**
 * This file routes requests to either the Symfony or Silex front controller.
 *
 * Expected phases:
 * 1) [CURRENT] Everything goes to Silex unless you're in < stable and the path starts with /s/
 * 2) Everything goes to Silex unless the path starts with /s/
 * 3) Everything goes to Symfony unless the path starts with /x/
 * 4) Everything goes to Symfony
 */


/**
 * In and after phase 2, we can move the autoload and instantiation of HpoApplication
 * back to the silex-index.php and remove the $allowSymfony check
 */

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
