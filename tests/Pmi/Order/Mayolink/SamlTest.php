<?php
use Pmi\Order\Mayolink\Saml;

class SamlTest extends \PHPUnit_Framework_TestCase
{
    public function testUuid()
    {
        $uuid = Saml::generateUuid();
        $this->assertRegExp('/^[0-9A-F]{40}$/', $uuid);
        $uuid2 = Saml::generateUuid();
        $this->assertNotEquals($uuid, $uuid2);
    }

    public function testSamlAuthn()
    {
        $saml = Saml::generateAuthnRequest('example.com');
        $this->assertStringStartsWith('<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol', $saml);
        $this->assertRegExp('/IssueInstant="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}"/', $saml);
        $this->assertRegExp('/ProviderName="example.com"/', $saml);
        $this->assertRegExp('/ID="[0-9A-F]{40}"/', $saml);
    }
}
