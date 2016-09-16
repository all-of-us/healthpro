<?php
use Pmi\Controller;
use Tests\Pmi\AbstractWebTestCase;

class HpoApplicationTest extends AbstractWebTestCase
{
    public function testController()
    {
        $this->app->mount('/', new Controller\DefaultController());
        $client = $this->createClient();
        $crawler = $client->request('GET', '/');
        $this->assertEquals(403, $client->getResponse()->getStatusCode());
    }
}
