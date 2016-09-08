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

    public function create($options)
    {
        $body = [
            'authenticity_token' => $this->csrfToken,
            'order[test_requests_attributes][0][test_code]' => $options['test_code'],
            "temperatures[{$options['test_code']}][{$options['specimen']}]" => $options['temperature'],
            'order[patient_attributes][first_name]' => $options['first_name'],
            'order[patient_attributes][last_name]' => $options['last_name'],
            'order[patient_attributes][gender]' => $options['gender'],
            'order[patient_attributes][birth_date]' => $options['birth_date']->format('Y-m-d'),
            'order[physician_name]' => $options['physician_name'],
            'order[physician_phone]' => $options['physician_phone'],
            'order[collected_at(1i)]' => $options['collected_at']->format('Y'),
            'order[collected_at(2i)]' => $options['collected_at']->format('n'),
            'order[collected_at(3i)]' => $options['collected_at']->format('j'),
            'order[collected_at(4i)]' => $options['collected_at']->format('H'),
            'order[collected_at(5i)]' => $options['collected_at']->format('i')
        ];
        $response = $this->client->request('POST', "{$this->ordersEndpoint}/en/orders", [
            'form_params' => $body,
            'allow_redirects' => false
        ]);
        if ($response->getStatusCode() !== 302 || empty($response->getHeader('Location'))) {
            return false;
        }
        $location = $response->getHeader('Location')[0];
        if (!preg_match('/orders\/(.*)$/', $location, $matches)) {
            return false;
        }
        return $matches[1];
    }

    public function loginAndCreateOrder($username, $password, $options)
    {
        $this->login($username, $password);
        return $this->create($options);
    }
}
