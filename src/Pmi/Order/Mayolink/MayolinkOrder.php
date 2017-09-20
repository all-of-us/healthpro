<?php
namespace Pmi\Order\Mayolink;

use Silex\Application;
use Pmi\HttpClient;

class MayolinkOrder
{
    protected $ordersEndpoint = 'https://test.orders.mayomedicallaboratories.com';
    protected $labelPdf = 'orders/labels.xml';
    protected $createOrder = 'orders/create.xml';

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

    public function __construct(Application $app)
    {
        $this->client = new HttpClient(['cookies' => true]);
        if ($app->getConfig('ml_orders_endpoint')) {
            $this->ordersEndpoint = $app->getConfig('ml_orders_endpoint');
        }
    }

    public function createOrder($username, $password, $options)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<orders xmlns="'.$this->ordersEndpoint.'">';
        $xml .= '<order>';
        $xml .= '<collected>'.$options['collected_at']->format('c').'</collected>';
        $xml .= '<account>'.$options['mayoClientId'].'</account>';
        $xml .= '<number>'.$options['order_id'].'</number>';
        $xml .= '<patient>';
        $xml .= '<medical_record_number>'.$options['patient_id'].'</medical_record_number>';
        $xml .= '<first_name>'.$options['first_name'].'</first_name>';
        $xml .= '<last_name>'.$options['last_name'].'</last_name>';
        $xml .= '<middle_name/>';
        $xml .= '<birth_date>'.$options['birth_date']->format('Y-m-d').'</birth_date>';
        $xml .= '<gender>'.$options['gender'].'</gender>';
        $xml .= '<address1/>';
        $xml .= '<address2/>';
        $xml .= '<city/>';
        $xml .= '<state/>';
        $xml .= '<postal_code/>';
        $xml .= '<phone/>';
        $xml .= '<account_number/>';
        $xml .= '<race/>';
        $xml .= '<ethnic_group/>';
        $xml .= '</patient>';
        $xml .= '<physician>';
        $xml .= '<name>'.$options['siteId'].'</name>';
        $xml .= '<phone>'.$options['organizationId'].'</phone>';
        $xml .= '<npi/>';
        $xml .= '</physician>';
        $xml .= '<report_notes/>';
        $xml .= '<tests>';
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = self::$salivaTests;
        } else {
            $tests = self::$tests;
        }
        if ($options['finalized_samples']) {
            $samples = json_decode($options['finalized_samples']);
            foreach ($samples as $key => $sample) {
                $xml .= '<test>';
                $xml .= '<code>'.$sample.'</code>';
                $xml .= '<name>'.$tests[$sample]['specimen'].'</name>';
                $xml .= '<comments/>';
                $xml .= '</test>';
            }
        } else {
            foreach ($tests as $key => $sample) {
                $xml .= '<test>';
                $xml .= '<code>'.$key.'</code>';
                $xml .= '<name>'.$sample['specimen'].'</name>';
                $xml .= '<comments/>';
                $xml .= '</test>';
            }
        }
        $xml .= '</tests>';
        $xml .= '<comments/>';
        $xml .= '</order>';
        $xml .= '</orders>';
        $response = $this->client->request('POST', "{$this->ordersEndpoint}/{$this->createOrder}", [
            'auth' => [$username, $password],
            'body' => $xml
        ]);
        if ($response->getStatusCode() !== 201) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $mayoId = $xmlObj->order->number;
        return $mayoId;
    }

    public function getLabelsPdf($username, $password, $options)
    {
        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<orders xmlns="'.$this->ordersEndpoint.'">';
        $xml .= '<order>';
        $xml .= '<collected>'.$options['collected_at']->format('c').'</collected>';
        $xml .= '<account>'.$options['mayoClientId'].'</account>';
        $xml .= '<number>'.$options['order_id'].'</number>';
        $xml .= '<patient>';
        $xml .= '<medical_record_number>'.$options['patient_id'].'</medical_record_number>';
        $xml .= '<first_name>'.$options['first_name'].'</first_name>';
        $xml .= '<last_name>'.$options['last_name'].'</last_name>';
        $xml .= '<middle_name/>';
        $xml .= '<birth_date>'.$options['birth_date']->format('Y-m-d').'</birth_date>';
        $xml .= '<gender>'.$options['gender'].'</gender>';
        $xml .= '</patient>';
        $xml .= '<tests>';
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = self::$salivaTests;
        } else {
            $tests = self::$tests;
        }
        if ($options['requested_samples']) {
            $samples = json_decode($options['requested_samples']);
            foreach ($samples as $key => $sample) {
                $xml .= '<test>';
                $xml .= '<code>'.$sample.'</code>';
                $xml .= '<name>'.$tests[$sample]['specimen'].'</name>';
                $xml .= '</test>';
            }
        } else {
            foreach ($tests as $key => $sample) {
                $xml .= '<test>';
                $xml .= '<code>'.$key.'</code>';
                $xml .= '<name>'.$sample['specimen'].'</name>';
                $xml .= '</test>';
            }
        }
        $xml .= '</tests>';
        $xml .= '</order>';
        $xml .= '</orders>';
        $response = $this->client->request('POST', "{$this->ordersEndpoint}/{$this->labelPdf}", [
            'auth' => [$username, $password],
            'body' => $xml
        ]);
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $pdf = base64_decode($xmlObj->order->labels);
        return $pdf;
    }

    public function getRequisitionPdf($username, $password, $id)
    {
        $response = $this->client->request('GET', "{$this->ordersEndpoint}/orders/{$id}.xml", [
            'auth' => [$username, $password]
        ]);
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $pdf = base64_decode($xmlObj->order->requisition);
        return $pdf;
    }
}
