<?php

namespace App\Test\Entity;

use App\Entity\Measurement;
use App\Entity\User;
use App\Exception\MissingSchemaException;
use PHPUnit\Framework\TestCase;

class MeasurementTest extends TestCase
{
    protected function getUser()
    {
        $user = new User;
        $user->setEmail('test@example.com');
        return $user;
    }

    public function createMeasurement($params = [])
    {
        $measurement = new Measurement();
        $measurement->setUser($params['user']);
        $measurement->setSite($params['site']);
        $measurement->setParticipantId($params['participantId']);
        $measurement->setCreatedTs($params['ts']);
        $measurement->setUpdatedTs($params['updatedTs'] ?? $params['ts']);
        $measurement->setFinalizedUser($params['finalizedUser'] ?? $params['user']);
        $measurement->setFinalizedSite($params['finalizedSite'] ?? $params['site']);
        $measurement->setFinalizedTs($params['finalizedTs'] ?? $params['ts']);
        $measurement->setData($params['data']);
        $measurement->setVersion($params['version']);
        return $measurement;
    }

    /**
     * Normalize FHIR bundle URNs
     *
     * Replaces each unique URN UUID with uuid:n in order for easier test comparsion
     **/
    public static function getNormalizedFhir($fhir)
    {
        $map = [];
        $map[$fhir->entry[0]['fullUrl']] = 'uuid:composition';
        foreach ($fhir->entry[0]['resource']['section'][0]['entry'] as $k => $entry) {
            $map[$entry['reference']] = 'uuid:' . ($k + 1);
        }
        $json = json_encode($fhir);
        $json = str_replace(array_keys($map), array_values($map), $json);
        return json_decode($json);
    }

    public function measurementsProvider()
    {
        return [
            [
                'fhir-normal.json',
                '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,101,102],"blood-pressure-diastolic":[80,81,82],"heart-rate":[85,88,90],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height":180,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-circumference":[90,91,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[85,88,87],"waist-circumference-location":"smallest-part-of-trunk","waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}'
            ],
            [
                'fhir-modifications.json',
                '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,null,102],"blood-pressure-diastolic":[80,null,82],"heart-rate":[85,null,90],"irregular-heart-rate":[true,false,false],"blood-pressure-protocol-modification":["","refusal","other"],"manual-blood-pressure":[false,false,true],"manual-heart-rate":[false,false,true],"blood-pressure-protocol-modification-notes":[null,null,"Some reason"],"pregnant":true,"wheelchair":true,"height":180,"height-protocol-modification":"wheelchair-user","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":55,"weight-protocol-modification":"wheelchair-user","weight-protocol-modification-notes":null,"hip-circumference":[null,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[null,null,null],"waist-circumference-location":null,"waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":"Some notes"}'
            ]
        ];
    }

    public function bloodDonorMeasurementsProvider()
    {
        return [
            [
                'blood-donor-fhir.json',
                '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,null,null],"blood-pressure-diastolic":[80,null,null],"heart-rate":[85,null,null],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height":null,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-circumference":[null,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[null,null,null],"waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}'
            ]
        ];
    }

    public function ehrSourceMeasurementsProvider()
    {
        return [
            [
                'ehr-measurement-source-fhir.json',
                '{"blood-pressure-location":"Right arm","blood-pressure-source":"ehr","blood-pressure-source-ehr-date":"2017-01-01","blood-pressure-systolic":[100,null,null],"blood-pressure-diastolic":[80,null,null],"heart-rate":[85,null,null],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height-source":"ehr","height-source-ehr-date":"2017-01-01","height":180,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight-source":"ehr","weight-source-ehr-date":"2017-01-01","weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-circumference-source":"ehr","hip-circumference-source-ehr-date":"2017-01-01","hip-circumference":[90,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference-source":"ehr","waist-circumference-source-ehr-date":"2017-01-01","waist-circumference":[85,null,null],"waist-circumference-location":"smallest-part-of-trunk","waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}'
            ]
        ];
    }

    public function testSchema()
    {
        $measurement = new Measurement;
        $measurement->loadFromAObject();
        $schema = $measurement->getSchema();
        $this->assertEquals(Measurement::CURRENT_VERSION, $schema->version);
        $this->assertTrue(is_array($schema->fields));
    }

    public function testMissingSchema()
    {
        $this->expectException(MissingSchemaException::class);
        $measurement = new Measurement;
        $measurement->setParticipantId('test');
        $measurement->setVersion('0.0a');
        $measurement->loadFromAObject();
        $measurement->getSchema();
    }

    /**
     * @dataProvider measurementsProvider
     */
    public function testFhir($filename, $jsonData)
    {
        $measurementArray = [
            'user' => $this->getUser(),
            'site' => 'test-site1',
            'ts' => new \DateTime('2017-01-01', new \DateTimeZone('UTC')),
            'participantId' => 'P10000001',
            'finalizedSite' => 'test-site2',
            'data' => $jsonData,
            'version' => '0.3.3'
        ];
        $measurement = $this->createMeasurement($measurementArray);
        $measurement->loadFromAObject($this->getUser()->getEmail(), $measurementArray['finalizedSite']);
        $measurement->getFhir($measurementArray['ts']);
        $fhir = self::getNormalizedFhir($measurement->getFhir($measurementArray['ts']));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);

        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/../' . $filename), $json);
    }


    /**
     * @dataProvider bloodDonorMeasurementsProvider
     */
    public function testBloodDonorFhir($filename, $jsonData)
    {
        $measurementArray = [
            'user' => $this->getUser(),
            'site' => 'test-site1',
            'ts' => new \DateTime('2017-01-01', new \DateTimeZone('UTC')),
            'participantId' => 'P10000001',
            'finalizedSite' => 'test-site2',
            'data' => $jsonData,
            'version' => '0.3.3-blood-donor'
        ];
        $measurement = $this->createMeasurement($measurementArray);
        $measurement->loadFromAObject($this->getUser()->getEmail(), $measurementArray['finalizedSite']);
        $measurement->addBloodDonorProtocolModificationForRemovedFields();
        $fhir = self::getNormalizedFhir($measurement->getFhir($measurementArray['ts']));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);
        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/../' . $filename), $json);
    }

    /**
     * @dataProvider ehrSourceMeasurementsProvider
     */
    public function testEHRMeasurementSourceFhir($filename, $jsonData)
    {
        $measurementArray = [
            'user' => $this->getUser(),
            'site' => 'test-site1',
            'ts' => new \DateTime('2017-01-01', new \DateTimeZone('UTC')),
            'participantId' => 'P10000001',
            'finalizedSite' => 'test-site2',
            'data' => $jsonData,
            'version' => '0.3.3-ehr'
        ];
        $measurement = $this->createMeasurement($measurementArray);
        $measurement->loadFromAObject($this->getUser()->getEmail(), $measurementArray['finalizedSite']);
        $fhir = self::getNormalizedFhir($measurement->getFhir($measurementArray['ts']));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);
        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/../' . $filename), $json);
    }
}
