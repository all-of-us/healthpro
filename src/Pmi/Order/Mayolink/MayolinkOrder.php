<?php
namespace Pmi\Order\Mayolink;

class MayolinkOrder
{
    protected $ordersEndpoint = 'https://orders.mayomedicallaboratories.com';
    protected $authEndpoint = 'https://profile.mayomedicallaboratories.com/authn';
    protected $providerName = 'www.mayomedicallaboratories.com';
    protected static $tests = [
        '1ED04' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
        ],
        '1ED10' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
        ],
        '1SST8' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Serum SST'
        ],
        '1PST8' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Plasma PST'
        ],
        '2ED10' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
        ],
        '1HEP4' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'WB Sodium Heparin'
        ],
        '1UR10' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Urine'
        ]
    ];
    protected static $salivaTests = [
        '1SAL' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Saliva'
        ]
    ];
    protected static $siteAccounts = [
        'a' => '7035588',
        'b' => '7035500',
        'uofacats' => '7035650',
        'bannerscampus' => '7035651',
        'bannerphoenix' => '7035652',
        'bannerestrella' => '7035653',
        'bannerdesert' => '7035654'
    ];

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
            'order[reference_number]' => $options['order_id'],
            'order[patient_attributes][medical_record_number]' => $options['patient_id'],
            'order[patient_attributes][first_name]' => '*',
            'order[patient_attributes][last_name]' => $options['patient_id'],
            'order[patient_attributes][gender]' => $options['gender'],
            'order[patient_attributes][birth_date]' => $options['birth_date']->format('Y-m-d'),
            'order[physician_name]' => 'None',
            'order[physician_phone]' => 'None',
            'order[collected_at(1i)]' => $options['collected_at']->format('Y'),
            'order[collected_at(2i)]' => $options['collected_at']->format('n'),
            'order[collected_at(3i)]' => $options['collected_at']->format('j'),
            'order[collected_at(4i)]' => $options['collected_at']->format('H'),
            'order[collected_at(5i)]' => $options['collected_at']->format('i')
        ];
        $i = 0;
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = self::$salivaTests;
        } else {
            $tests = self::$tests;
        }
        foreach ($tests as $test => $testOptions) {
            if (isset($options['tests']) && !in_array($test, $options['tests'])) {
                continue;
            }
            $body["order[test_requests_attributes][{$i}][test_code]"] = $test;
            $body["temperatures[{$test}][{$testOptions['specimen']}]"] = $testOptions['temperature'];
            $i++;
        }
        if (isset($options['site']) && isset(self::$siteAccounts[$options['site']])) {
            $body['account'] = self::$siteAccounts[$options['site']];
        }
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

    public function getPdf($id, $type)
    {
        if ($type == 'labels') {
            $response = $this->client->request('GET', "{$this->ordersEndpoint}/en/orders/{$id}/label-set");
        } elseif ($type == 'requisition') {
            $response = $this->client->request('GET', "{$this->ordersEndpoint}/en/orders/{$id}/requisition");
        } else {
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $stream = $response->getBody();
        return $stream->getContents();
    }

    public function loginAndCreateOrder($username, $password, $options)
    {
        if ($this->login($username, $password)) {
            return $this->create($options);
        } else {
            return false;
        }
    }

    public function loginAndGetPdf($username, $password, $id, $type)
    {
        if ($this->login($username, $password)) {
            return $this->getPdf($id, $type);
        } else {
            return false;
        }
    }
}
