<?php
use Pmi\Application\HpoApplication;
use Pmi\Controller;

class HpoApplicationTest extends \PHPUnit_Framework_TestCase
{
    public function testApplication()
    {
        putenv('PMI_ENV=' . HpoApplication::ENV_DEV);
        $app = new HpoApplication([
            'templatesDirectory' => __DIR__ . '/../../../views',
            'errorTemplate' => 'error.html.twig',
            'isUnitTest' => true
        ]);
        $app->setup();
        $app->register(new \Silex\Provider\SessionServiceProvider(), [
            'session.test' => true
        ]);

        $this->assertArrayHasKey('locale', $app);
        $this->assertArrayHasKey('translator', $app);
        $this->assertArrayHasKey('form.factory', $app);
        $this->assertArrayHasKey('translator', $app);
        $this->assertArrayHasKey('validator', $app);
        $this->assertArrayHasKey('twig', $app);
        $this->assertArrayHasKey('pmi.drc.participantsearch', $app);

        $app->boot();

        return $app;
    }

    /**
     * @depends testApplication
     */
    public function testController($app)
    {
        $app->mount('/', new Controller\DefaultController());
        ob_start();
        $app->run();
        $output = ob_get_clean();
        $this->assertRegExp('/Authentication Required/', $output);
    }
}
