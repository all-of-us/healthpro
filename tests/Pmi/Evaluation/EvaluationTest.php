<?php
namespace Tests\Pmi\Evaluation;

use Pmi\Evaluation\Evaluation;
use Pmi\Evaluation\MissingSchemaException;
use Symfony\Component\Form\Form;
use Tests\Pmi\AbstractWebTestCase;

class EvaluationTest extends AbstractWebTestCase
{
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
            $map[$entry['reference']] = 'uuid:' . ($k+1);
        }
        $json = json_encode($fhir);
        $json = str_replace(array_keys($map), array_values($map), $json);
        return json_decode($json);
    }

    public function measurementsProvider()
    {
        return [
            ['fhir-normal.json', '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,101,102],"blood-pressure-diastolic":[80,81,82],"heart-rate":[85,88,90],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height":180,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-circumference":[90,91,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[85,88,87],"waist-circumference-location":"smallest-part-of-trunk","waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}'],
            ['fhir-modifications.json', '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,null,102],"blood-pressure-diastolic":[80,null,82],"heart-rate":[85,null,90],"irregular-heart-rate":[true,false,false],"blood-pressure-protocol-modification":["","refusal","other"],"manual-blood-pressure":[false,false,true],"manual-heart-rate":[false,false,true],"blood-pressure-protocol-modification-notes":[null,null,"Some reason"],"pregnant":true,"wheelchair":true,"height":180,"height-protocol-modification":"wheelchair-user","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":55,"weight-protocol-modification":"wheelchair-user","weight-protocol-modification-notes":null,"hip-circumference":[null,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[null,null,null],"waist-circumference-location":null,"waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":"Some notes"}']
        ];
    }

    public function diversionPouchMeasurementsProvider()
    {
        return [
            ['diversion-pouch-fhir.json', '{"blood-pressure-location":"Right arm","blood-pressure-systolic":[100,null,null],"blood-pressure-diastolic":[80,null,null],"heart-rate":[85,null,null],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height":null,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-circumference":[null,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-circumference":[null,null,null],"waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}']
        ];
    }

    public function ehrSourceMeasurementsProvider()
    {
        return [
            ['ehr-measurement-source-fhir.json', '{"blood-pressure-location":"Right arm","blood-pressure-source":"ehr","blood-pressure-source-ehr-date":"2017-01-01","blood-pressure-systolic":[100,null,null],"blood-pressure-diastolic":[80,null,null],"heart-rate":[85,null,null],"irregular-heart-rate":[false,false,false],"blood-pressure-protocol-modification":["","",""],"manual-blood-pressure":[false,false,false],"manual-heart-rate":[false,false,false],"blood-pressure-protocol-modification-notes":[null,null,null],"pregnant":false,"wheelchair":false,"height-source":"ehr","height-source-ehr-date":"2017-01-01","height":180,"height-protocol-modification":"","height-protocol-modification-notes":null,"weight-source":"ehr","weight-source-ehr-date":"2017-01-01","weight":65,"weight-prepregnancy":null,"weight-protocol-modification":"","weight-protocol-modification-notes":null,"hip-source":"ehr","hip-source-ehr-date":"2017-01-01","hip-circumference":[90,null,null],"hip-circumference-protocol-modification":["","",""],"hip-circumference-protocol-modification-notes":[null,null,null],"waist-source":"ehr","waist-source-ehr-date":"2017-01-01","waist-circumference":[85,null,null],"waist-circumference-location":"smallest-part-of-trunk","waist-circumference-protocol-modification":["","",""],"waist-circumference-protocol-modification-notes":[null,null,null],"notes":null}']
        ];
    }

    public function testSchema()
    {
        $evaluation = new Evaluation();
        $schema = $evaluation->getSchema();
        $this->assertEquals(Evaluation::CURRENT_VERSION, $schema->version);
        $this->assertTrue(is_array($schema->fields));
    }

    public function testMissingSchema()
    {
        $this->expectException(MissingSchemaException::class);
        $evaluation = new Evaluation();
        $evaluation->loadFromArray(['version' => '0.0a', 'participant_id' => 'test']);
        $schema = $evaluation->getSchema();
    }

    public function testForm()
    {
        $evaluation = new Evaluation();
        $form = $evaluation->getForm($this->app['form.factory']);
        $this->assertInstanceOf(Form::class, $form);
    }

    /**
     * @dataProvider measurementsProvider
     */
    public function testFhir($filename, $jsonData)
    {
        $finalized = new \DateTime('2017-01-01', new \DateTimeZone('UTC'));
        $evaluation = new Evaluation();
        $evaluation->loadFromArray([
            'data' => json_decode($jsonData),
            'participant_id' => 'P10000001',
            'created_user' => 'test@example.com',
            'finalized_user' => 'test@example.com',
            'created_site' => 'test-site1',
            'finalized_site' => 'test-site2',
        ]);
        $fhir = self::getNormalizedFhir($evaluation->getFhir($finalized));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);

        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/' . $filename), $json);
    }

    /**
     * @dataProvider diversionPouchMeasurementsProvider
     */
    public function testDiversionPouchFhir($filename, $jsonData)
    {
        $finalized = new \DateTime('2017-01-01', new \DateTimeZone('UTC'));
        $evaluation = new Evaluation();
        $evaluation->loadFromArray([
            'data' => json_decode($jsonData),
            'participant_id' => 'P10000001',
            'created_user' => 'test@example.com',
            'finalized_user' => 'test@example.com',
            'created_site' => 'test-site1',
            'finalized_site' => 'test-site2',
            'version' => '0.3.3-diversion-pouch'
        ]);
        $evaluation->addBloodDonorProtocolModificationForRemovedFields();
        $fhir = self::getNormalizedFhir($evaluation->getFhir($finalized));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);
        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/' . $filename), $json);
    }

    /**
     * @dataProvider ehrSourceMeasurementsProvider
     */
    public function testEHRMeasurementSourceFhir($filename, $jsonData)
    {
        $finalized = new \DateTime('2017-01-01', new \DateTimeZone('UTC'));
        $evaluation = new Evaluation();
        $evaluation->loadFromArray([
            'data' => json_decode($jsonData),
            'participant_id' => 'P10000001',
            'created_user' => 'test@example.com',
            'finalized_user' => 'test@example.com',
            'created_site' => 'test-site1',
            'finalized_site' => 'test-site2',
            'version' => '0.3.3-ehr'
        ]);
        $fhir = self::getNormalizedFhir($evaluation->getFhir($finalized));
        $json = json_encode($fhir, JSON_PRETTY_PRINT);
        // using string to string method so that diff is output (file to string just shows entire object)
        $this->assertJsonStringEqualsJsonString(file_get_contents(__DIR__ . '/' . $filename), $json);
    }
}
