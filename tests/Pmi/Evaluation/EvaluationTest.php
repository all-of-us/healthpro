<?php
use Pmi\Evaluation\Evaluation;
use Pmi\Evaluation\MissingSchemaException;
use Symfony\Component\Form\Form;
use Tests\Pmi\AbstractWebTestCase;

class EvaluationTest extends AbstractWebTestCase
{
    public function testSchema()
    {
        $evaluation = new Evaluation();
        $schema = $evaluation->getSchema();
        $this->assertEquals(Evaluation::CURRENT_VERSION, $schema->version);
        $this->assertTrue(is_array($schema->fields));
    }

    public function testMissingSchema()
    {
        $this->setExpectedException(MissingSchemaException::class);
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

    public function testFhir()
    {
        $finalized = new \DateTime();
        $evaluation = new Evaluation();
        $testHeartRates = [100,99,98];
        $evaluation->loadFromArray([
            'data' => (object)[
                'heart-rate' => $testHeartRates,
                'height' => 175,
                'weight' => 73,
            ],
            'participant_id' => 'P10000001',
            'created_user' => 'test-user1@example.com',
            'finalized_user' => 'test-user2@example.com',
            'created_site' => 'test-site1',
            'finalized_site' => 'test-site2',
        ]);
        $fhir = $evaluation->getFhir($finalized);
        $this->assertTrue(is_object($fhir));
        $this->assertSame('Bundle', $fhir->resourceType);
        $this->assertSame('document', $fhir->type);
        $this->assertTrue(is_array($fhir->entry));
        $entries = $fhir->entry;

        $composition = array_shift($entries);
        $this->assertSame('Composition', $composition['resource']['resourceType']);
        $this->assertSame("Patient/P10000001", $composition['resource']['subject']['reference']);
        $this->assertTrue(is_array($composition['resource']['section'][0]['entry']));
        $this->assertSame([
            [
                'reference' => "Practitioner/test-user1@example.com",
                'extension' => [
                    'url' => "http://terminology.pmi-ops.org/StructureDefinition/authoring-step",
                    'valueCode' => "created"
                ]
            ],
            [
                'reference' => "Practitioner/test-user2@example.com",
                'extension' => [
                    'url' => "http://terminology.pmi-ops.org/StructureDefinition/authoring-step",
                    'valueCode' => "finalized"
                ]
            ]
        ], $composition['resource']['author']);
        $this->assertSame([
            [
                'url' => "http://terminology.pmi-ops.org/StructureDefinition/authored-location",
                'valueReference' => "Location/test-site1"
            ],
            [
                'url' => "http://terminology.pmi-ops.org/StructureDefinition/finalized-location",
                'valueReference' => "Location/test-site2"
            ]
        ], $composition['resource']['extension']);
        $references = [];
        foreach ($composition['resource']['section'][0]['entry'] as $refEntry) {
            $references[] = $refEntry['reference'];
            $this->assertRegExp('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $refEntry['reference']);
        }
        $this->assertSame(6, count($references));

        $this->assertSame(6, count($entries));
        for ($i=0; $i<3; $i++) {
            $r = $i+1;
            $this->assertSame($references[$i], $entries[$i]['fullUrl']);
            $this->assertSame([
                'coding' => [[
                    'code' => '8867-4',
                    'display' => 'Heart rate',
                    'system' => 'http://loinc.org'
                ]],
                'text' => 'Heart rate'
            ], $entries[$i]['resource']['code']);
            $this->assertSame([
                'code' => '/min',
                'system' => 'http://unitsofmeasure.org',
                'unit' => '/min',
                'value' => $testHeartRates[$i]
            ], $entries[$i]['resource']['valueQuantity']);
        }
        $this->assertSame([
            'code' => 'cm',
            'system' => 'http://unitsofmeasure.org',
            'unit' => 'cm',
            'value' => 175
        ], $entries[3]['resource']['valueQuantity']);
        $this->assertSame([
            'code' => 'kg',
            'system' => 'http://unitsofmeasure.org',
            'unit' => 'kg',
            'value' => 73
        ], $entries[4]['resource']['valueQuantity']);
        $this->assertSame([
            'code' => 'kg/m2',
            'system' => 'http://unitsofmeasure.org',
            'unit' => 'kg/m2',
            'value' => 23.8
        ], $entries[5]['resource']['valueQuantity']);
    }
}
