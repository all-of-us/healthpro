<?php

namespace App\Test\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HealthProApplicationTest extends WebTestCase
{
    public function testController()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/s');
        $this->assertEquals(302, $client->getResponse()->getStatusCode());
    }
}
