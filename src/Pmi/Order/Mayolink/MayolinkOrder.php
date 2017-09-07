<?php
namespace Pmi\Order\Mayolink;

use Silex\Application;
use Pmi\HttpClient;

class MayolinkOrder
{
    protected $ordersEndpoint = 'https://orders.mayomedicallaboratories.com';
    protected $authEndpoint = 'https://profile.mayomedicallaboratories.com/authn';
    protected $providerName = 'www.mayomedicallaboratories.com';
    protected $labelPdf = '/orders/labels.xml';
    protected $requisitionPdf = '/orders/requisition.xml';
    protected $createOrder = '/orders/create.xml';

    protected static $tests = [
        '1SST8' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Serum SST'
        ],
        '1PST8' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Plasma PST'
        ],
        '1HEP4' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'WB Sodium Heparin'
        ],
        '1ED04' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
        ],
        '1ED10' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
        ],
        '2ED10' => [
            'temperature' => 'Refrigerated',
            'specimen' => 'Whole Blood EDTA'
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

    private $client;
    private $csrfToken;

    public function __construct(Application $app)
    {
        $this->client = new HttpClient(['cookies' => true]);
        $configurationMapping = [
            'ordersEndpoint' => 'ml_orders_endpoint',
            'authEndpoint' => 'ml_auth_endpoint',
            'providerName' => 'ml_provider_name',
            'labelPdf' => 'ml_label_pdf',
            'requisitionPdf' => 'ml_requisition_pdf'
        ];

        foreach ($configurationMapping as $variable => $configName) {
            if ($value = $app->getConfig($configName)) {
                $this->$variable = $value;
            }
        }
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
            'order[collected]' => $options['collected_at'],
            'order[account]' => $options['mayoClientId'],
            'order[number]' => $options['order_id'],
            'order[patient][medical_record_number]' => $options['patient_id'],
            'order[patient][birth_date]' => $options['birth_date']->format('Y-m-d'),
            'order[patient][gender]' => $options['gender'],
            'order[physician][name]' => $options['siteId'],
            'order[physician][phone]' => $options['organizationId']
        ];
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = self::$salivaTests;
        } else {
            $tests = self::$tests;
        }
        if ($options['finalized_samples']) {
            $samples = json_decode($options['finalized_samples']);
            foreach ($samples as $key => $sample) {
                $body["order[tests][{$key}][test][code]"] = $sample;
                $body["order[tests][{$key}][test][name]"] = $tests[$sample]['specimen'];
            }
        } else {
            $i = 0;
            foreach ($tests as $key => $sample) {
                $body["order[tests][{$i}][test][code]"] = $key;
                $body["order[tests][{$i}][test][name]"] = $sample['specimen'];
                $i++;
            }
        }
        echo '<pre>'; print_r($body); exit;
        $response = $this->client->request('POST', "{$this->ordersEndpoint}/{$createOrder}", [
            'form_params' => $body,
            'allow_redirects' => false
        ]);
        if ($response->getStatusCode() !== 302 || empty($response->getHeader('Location'))) {
            return false;
        }

        $xml = $response->getBody();
        $xmlObj = simplexml_load_string($xml);
        $mayoId = $xmlObj->order->reference_number;
        return $mayoId;
    }

    public function getPdf($options)
    {
        $body = [
            'order[collected]' => $options['collected_at'],
            'order[account]' => $options['mayoClientId'],
            'order[number]' => $options['order_id'],
            'order[patient][medical_record_number]' => $options['patient_id'],
            'order[patient][birth_date]' => $options['birth_date']->format('Y-m-d'),
            'order[patient][gender]' => $options['gender']
        ];
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = self::$salivaTests;
        } else {
            $tests = self::$tests;
        }
        if ($options['requested_samples']) {
            $samples = json_decode($options['requested_samples']);
            foreach ($samples as $key => $sample) {
                $body["order[tests][{$key}][test][code]"] = $sample;
                $body["order[tests][{$key}][test][name]"] = $tests[$sample]['specimen'];
            }
        } else {
            $i = 0;
            foreach ($tests as $key => $sample) {
                $body["order[tests][{$i}][test][code]"] = $key;
                $body["order[tests][{$i}][test][name]"] = $sample['specimen'];
                $i++;
            }
        }

        $response = $this->client->request('GET', "{$this->ordersEndpoint}/{$this->labelPdf}", [
            'form_params' => $body,
            'allow_redirects' => false
        ]);

        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $xml = $response->getBody();
        $xmlObj = simplexml_load_string($xml);
        $pdf = base64_decode($xmlObj->order->labels);
        return $pdf;
    }

    public function getRequisitionPdf($id)
    {
        $response = $this->client->request('GET', "{$this->ordersEndpoint}/{$id}.xml", [
            'allow_redirects' => false
        ]);
        $xml = $response->getBody();
        $xmlObj = simplexml_load_string($xml);
        $pdf = base64_decode($xmlObj->order->requisition);
        return $pdf;          
    }

    public function loginAndCreateOrder($username, $password, $options)
    {
        if ($this->login($username, $password)) {
            return $this->create($options);
        } else {
            return false;
        }
    }

    public function loginAndGetPdf($username, $password, $options)
    {
        if ($this->login($username, $password)) {
            return $this->getPdf($options);
        } else {
            return false;
        }
    }

    public function loginAndGetRequisitionPdf($id)
    {
        if ($this->login($username, $password)) {
            return $this->getRequisitionPdf($id);
        } else {
            return false;
        }
    }
}
