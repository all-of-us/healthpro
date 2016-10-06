<?php
namespace Pmi\Evaluation;

class Fhir
{
    protected $data;
    protected $schema;
    protected $patient;
    protected $version;
    protected $date;

    public function __construct(array $options)
    {
        $this->data = $options['data'];
        $this->schema = $options['schema'];
        $this->patient = $options['patient'];
        $this->version = $options['version'];
        // Convert DateTime object to UTC timestamp
        // (can't use 'c' ISO 8601 format because that results in +00:00 instead of Z)
        $date = clone $options['datetime'];
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->date = $date->format('Y-m-d\TH:i:s\Z');
    }

    protected function getComposition()
    {
        return [
            'fullUrl' => 'urn:example:report',
            'resource' => [
                'author' => [['display' => 'N/A']],
                'date' => $this->date,
                'resourceType' => 'Composition',
                'section' => [[
                    'entry' => [
                        ['reference' => 'urn:example:height']
                    ]
                ]],
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'title' => 'PMI Intake Evaluation',
                'type' => [
                    'coding' => [[
                        'code' => "intake-exam-v{$this->version}",
                        'display' => "PMI Intake Evaluation v{$this->version}",
                        'system' => 'http://terminology.pmi-ops.org/document-types'
                    ]],
                    'text' => "PMI Intake Evaluation v{$this->version}"
                ]
            ]
        ];
    }

    public function toObject()
    {
        $fhir = new \StdClass();
        $fhir->entry = [];
        $fhir->resourceType = 'Bundle';
        $fhir->type = 'document';
        $fhir->entry[] = $this->getComposition();
        $heightEntry = [
            'fullUrl' => 'urn:example:height',
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => '8302-2',
                        'display' => 'Body height',
                        'system' => 'http://loinc.org'
                    ]],
                    'text' => 'Body height'
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueQuantity' => [
                    'code' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'unit' => 'cm',
                    'value' => $this->data->height
                ]
            ]
        ];
        $fhir->entry[] = $heightEntry;
        return $fhir;
    }
}
