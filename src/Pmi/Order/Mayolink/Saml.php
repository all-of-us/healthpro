<?php
namespace Pmi\Order\Mayolink;

class Saml
{
    public static function generateUuid()
    {
        return strtoupper(bin2hex(openssl_random_pseudo_bytes(20)));
    }

    public static function generateAuthnRequest($provider)
    {
        $samlTemplate = '<samlp:AuthnRequest ' . 
            'xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" ' . 
            'ID="%s" ' . 
            'Version="2.0" ' . 
            'IssueInstant="%s" ' . 
            'ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect" ' . 
            'ProviderName="%s"/>';

        return sprintf($samlTemplate, self::generateUuid(), date('c'), $provider);
    }
}
