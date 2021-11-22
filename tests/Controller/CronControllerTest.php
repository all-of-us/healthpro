<?php

namespace App\Tests\Controller;

class CronControllerTest extends AppWebTestCase
{
    public function testWithoutHeader()
    {
        $this->client->request('GET', '/cron/ping-test');
        $this->assertResponseStatusCodeSame(404, 'Returns a 404 without header set.');
    }

    public function testWitHeader()
    {
        $this->client->request('GET', '/cron/ping-test', [], [], [
            'HTTP_X_APPENGINE_CRON' => 'true'
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertEquals('{"success":true}', $this->client->getResponse()->getContent());
    }
}
