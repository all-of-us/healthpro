<?php

namespace App\Tests\Controller;

class LegacyRedirectControllerTest extends AppWebTestCase
{
    public function testGetRedirect()
    {
        $this->login('testLegacyRedirect@example.com');
        $this->client->request('GET', '/s/some-old-url');
        $this->assertResponseRedirects('/some-old-url', 308);
    }

    public function testPostRedirectWithData()
    {
        $postData = [
            'field1' => 'One',
            'field2' => 'Two'
        ];
        $this->login('testLegacyRedirect@example.com');
        $this->client->followRedirects();
        $this->client->request('POST', '/s/some-old-url', $postData);
        $redirectedRequest = $this->client->getRequest();
        $this->assertSame('/some-old-url', $redirectedRequest->getRequestUri());
        $this->assertSame($postData, $redirectedRequest->request->all());
    }
}
