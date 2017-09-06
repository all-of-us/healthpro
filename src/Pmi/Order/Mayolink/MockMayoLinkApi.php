<?php
namespace Pmi\Order\Mayolink;

use Silex\Application;
use Pmi\HttpClient;

class MockMayoLinkApi
{
    public function createResponse()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
                    <orders xmlns="http://orders.mayomedicallaboratories.com">
                        <order>
                            <account>7123456</account>
                            <number>WEB1ABCD1234</number>
                            <reference_number>ORD-123-456</reference_number>
                            <received>2016-12-01T12:00:00-05:00</received>
                            <status>Queued</status>
                            <patient>
                                <medical_record_number>PAT-123-456</medical_record_number>
                                <birth_date>1960-01-01</birth_date>
                                <sex>M</sex>
                                <last_name>Doe</last_name>
                                <first_name>John</first_name>
                                <middle_name></middle_name>
                            </patient>
                        </order>
                    </orders>';      
        return $xml;
    }

}
