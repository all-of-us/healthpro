<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CronControllerTest extends WebTestCase
{
    public function testWithoutHeader()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/s/cron/ping-test');

        $this->assertResponseStatusCodeSame('404', 'Returns a 404 without header set.');
    }

    public function testWitHeader()
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/s/cron/ping-test', [], [], [
            'HTTP_X_APPENGINE_CRON' => 'true'
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertEquals('{"success":true}', $client->getResponse()->getContent());
    }
}
