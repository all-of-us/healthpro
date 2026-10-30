<?php

namespace App\Tests\Controller;

class DefaultControllerTest extends AppWebTestCase
{
    public function testKeepAliveWithInvalidCsrfTokenReturnsForbiddenJson()
    {
        $this->login('testKeepAlive@example.com');
        $this->client->request('POST', '/keepalive', ['csrf_token' => 'invalid']);
        $this->assertResponseStatusCodeSame(403);
        $this->assertJson($this->client->getResponse()->getContent());
    }

    public function testKeepAliveWithValidCsrfToken()
    {
        $this->login('testKeepAliveValid@example.com');
        $csrfToken = 'test-keep-alive-token';
        $this->session->set('_csrf/keepAlive', $csrfToken);
        $this->session->save();

        $this->client->request('POST', '/keepalive', ['csrf_token' => $csrfToken]);
        $this->assertResponseIsSuccessful();
    }
}
