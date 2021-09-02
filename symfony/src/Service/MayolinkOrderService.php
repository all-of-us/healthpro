<?php

namespace App\Service;

use Pmi\HttpClient;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;
use App\Entity\Order;

class MayolinkOrderService
{
    protected $params;
    protected $em;
    protected $twig;
    protected $logger;
    protected $client;
    protected $ordersEndpoint;
    // This namespace is the same across all environments, regardless of the endpoint.
    // Also, note that this is just an XML namespace and is never used to make a request
    protected $nameSpace = 'http://orders.mayomedicallaboratories.com';
    protected $labelPdf = 'orders/labels.xml';
    protected $createOrder = 'orders/create.xml';
    protected $userName;
    protected $password;


    public function __construct(ParameterBagInterface $params, EntityManagerInterface $em, Environment $twig, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->em = $em;
        $this->twig = $twig;
        $this->logger = $logger;
        if (!$this->params->has('ml_mock_order')) {
            $this->setMayoCredentials();
        }
    }

    private function setMayoCredentials()
    {
        $this->client = new HttpClient(['cookies' => true]);
        $this->ordersEndpoint = $this->params->get('ml_orders_endpoint');
        $this->userName = $this->params->get('ml_username');
        $this->password = $this->params->get('ml_password');
        if (empty($this->ordersEndpoint) || empty($this->userName) || empty($this->password)) {
            throw new \Exception('MayoLINK connection is not configured.');
        }
    }

    public function createOrder($options)
    {
        $samples = $this->getSamples('collected', $options);
        $parameters = ['mayoUrl' => $this->nameSpace, 'options' => $options, 'samples' => $samples];
        $xmlFile = "mayolink/order-create.xml.twig";
        $xml = $this->twig->render($xmlFile, $parameters);
        try {
            $response = $this->client->request('POST', "{$this->ordersEndpoint}/{$this->createOrder}", [
                'auth' => [$this->userName, $this->password],
                'body' => $xml
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }
        if ($response->getStatusCode() !== 201) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $mayoId = $xmlObj->order->number;
        return $mayoId;
    }

    public function getLabelsPdf($options)
    {
        $samples = $this->getSamples('requested', $options);
        $parameters = ['mayoUrl' => $this->nameSpace, 'options' => $options, 'samples' => $samples];
        $xmlFile = "mayolink/order-labels.xml.twig";
        $xml = $this->twig->render($xmlFile, $parameters);
        try {
            $response = $this->client->request('POST', "{$this->ordersEndpoint}/{$this->labelPdf}", [
                'auth' => [$this->userName, $this->password],
                'body' => $xml
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $pdf = base64_decode($xmlObj->order->labels);
        return $pdf;
    }

    public function getRequisitionPdf($id)
    {
        try {
            $response = $this->client->request('GET', "{$this->ordersEndpoint}/orders/{$id}.xml", [
                'auth' => [$this->userName, $this->password]
            ]);
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $xmlResponse = $response->getBody();
        $xmlObj = simplexml_load_string($xmlResponse);
        $pdf = base64_decode($xmlObj->order->requisition);
        return $pdf;
    }

    public function getSamples($type, $options)
    {
        if (isset($options['type']) && $options['type'] === 'saliva') {
            $tests = $options['salivaTests'];
        } else {
            $tests = $options['tests'];
        }
        $mayoSamples = [];
        if ($options["{$type}_samples"]) {
            $samples = json_decode($options["{$type}_samples"]);
            foreach ($samples as $key => $sample) {
                if (!empty($options['centrifugeType']) && in_array($sample, Order::$samplesRequiringCentrifugeType)) {
                    $mayoSamples[] = [
                        'code' => $tests[$sample]['sampleId'],
                        'name' => $tests[$sample]['specimen'],
                        'questionCode' => $tests[$sample]['code'],
                        'questionPrompt' => $tests[$sample]['prompt'],
                        'questionAnswer' => Order::$centrifugeType[$options['centrifugeType']]
                    ];
                } else {
                    $sampleItems = [];
                    $sampleItems['code'] = $tests[$sample]['sampleId'];
                    $sampleItems['name'] = $tests[$sample]['specimen'];
                    if (!empty($tests[$sample]['labelCount'])) {
                        $sampleItems['labelCount'] = $tests[$sample]['labelCount'];
                    }
                    $mayoSamples[] = $sampleItems;
                }
            }
        } else {
            if ($type !== 'collected') {
                foreach ($tests as $key => $sample) {
                    $sampleItems = [];
                    $sampleItems['code'] = $tests[$key]['sampleId'];
                    $sampleItems['name'] = $sample['specimen'];
                    if (!empty($sample['labelCount'])) {
                        $sampleItems['labelCount'] = $sample['labelCount'];
                    }
                    $mayoSamples[] = $sampleItems;
                }
            }
        }
        return $mayoSamples;
    }
}
