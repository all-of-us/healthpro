<?php
namespace Pmi\Mayolink;

class Order
{
    protected $ordersEndpoint = 'https://orders.mayomedicallaboratories.com';
    protected $authEndpoint = 'https://profile.mayomedicallaboratories.com/authn';
    protected $providerName = 'www.mayomedicallaboratories.com';

    private $client;
    private $csrfToken;

    public function __construct()
    {
        $this->client = new \GuzzleHttp\Client(['cookies' => true]);
    }

    /**
     * Attempts to login and retrieve CSRF token
     */
    public function login($username, $password)
    {
        $body = [
            'SAMLRequest' => base64_encode(Saml::generateAuthnRequest($this->providerName)),
            'RelayState' => base64_encode("{$this->ordersEndpoint}/en/login"),
            'username' => $username,
            'password' => $password
        ];
        try {
            $response = $this->client->request('POST', $this->authEndpoint, [
                'form_params' => $body
            ]);
            if ($response->getStatusCode() !== 200) {
                return false;
            }
            $body = $response->getBody()->getContents();
            if (!preg_match('/name="authenticity_token" value="([^"]+)"/', $body, $matches)) {
                return false;
            }
            $this->csrfToken = $matches[1];
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
