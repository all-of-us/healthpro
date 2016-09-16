<?php
namespace tests\Pmi;

use Pmi\Application\HpoApplication;
use Silex\WebTestCase;

abstract class AbstractWebTestCase extends WebTestCase
{
    /** http://silex.sensiolabs.org/doc/master/testing.html#webtestcase */
    public function createApplication()
    {
        putenv('PMI_ENV=' . HpoApplication::ENV_DEV);
        $app = new HpoApplication([
            'templatesDirectory' => __DIR__ . '/../../views',
            'errorTemplate' => 'error.html.twig',
            'isUnitTest' => true
        ]);
        $app->setup();
        $app->register(new \Silex\Provider\SessionServiceProvider(), [
            'session.test' => true
        ]);
        return $app;
    }
}
