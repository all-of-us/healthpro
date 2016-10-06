<?php
namespace Pmi\Evaluation;

class Fhir
{
    protected $data;
    protected $schema;
    protected $patient;
    protected $version;
    protected $date;
    protected $metrics;

    public function __construct(array $options)
    {
        $this->data = $options['data'];
        $this->schema = $options['schema'];
        $this->patient = $options['patient'];
        $this->version = $options['version'];
        $this->metrics = $this->getMetrics();
        // Convert DateTime object to UTC timestamp
        // (can't use 'c' ISO 8601 format because that results in +00:00 instead of Z)
        $date = clone $options['datetime'];
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->date = $date->format('Y-m-d\TH:i:s\Z');
    }

    /*
     * Determines which metrics have values to represent
     */
    protected function getMetrics()
    {
        $metrics = [];
        foreach ($this->schema->fields as $field) {
            if (empty($this->data->{$field->name})) {
                continue;
            }
            if (!empty($field->replicates)) {
                foreach ($this->data->{$field->name} as $i => $value) {
                    if (empty($value)) {
                        continue;
                    }
                    $metrics[] = $field->name . '-' . ($i+1);
                }
            } else {
                $metrics[] = $field->name;
            }
        }
        return $metrics;
    }

    protected function getComposition()
    {
        $references = [];
        foreach ($this->metrics as $metric) {
            $references[] = ['reference' => 'urn:example:' . $metric];
        }
        return [
            'fullUrl' => 'urn:example:report',
            'resource' => [
                'author' => [['display' => 'N/A']],
                'date' => $this->date,
                'resourceType' => 'Composition',
                'section' => [[
                    'entry' => $references
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

    protected function simpleMetric($metric, $value, $display, $loinc, $unit)
    {
        return [
            'fullUrl' => 'urn:example:' . $metric,
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => $loinc,
                        'display' => $display,
                        'system' => 'http://loinc.org'
                    ]],
                    'text' => $display
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueQuantity' => [
                    'code' => $unit,
                    'system' => 'http://unitsofmeasure.org',
                    'unit' => $unit,
                    'value' => $value
                ]
            ]
        ];
    }

    protected function height()
    {
        return $this->simpleMetric(
            'height',
            $this->data->height,
            'Body height',
            '8302-2',
            'cm'
        );
    }

    protected function weight()
    {
        return $this->simpleMetric(
            'weight',
            $this->data->weight,
            'Body weight',
            '29463-7',
            'kg'
        );
    }

    protected function heartrate()
    {
        return $this->simpleMetric(
            'heart-rate',
            $this->data->{'heart-rate'},
            'Heart rate',
            '8867-4',
            '/min'
        );
    }

    protected function hipcircumference($replicate)
    {
        return $this->simpleMetric(
            'hip-circumference-' . $replicate,
            $this->data->{'hip-circumference'}[$replicate - 1],
            'Hip circumference',
            '62409-8',
            'cm'
        );
    }

    protected function waistcircumference($replicate)
    {
        return $this->simpleMetric(
            'waist-circumference-' . $replicate,
            $this->data->{'waist-circumference'}[$replicate - 1],
            'Waist circumference',
            '62409-8',
            'cm'
        );
    }

    protected function getEntry($metric)
    {
        if (preg_match('/^(.+)-(\d+)$/', $metric, $m)) {
            $replicate = $m[2];
            $method = $m[1];
        } else {
            $replicate = false;
            $method = $metric;
        }
        $method = str_replace('-', '', $method);
        if (method_exists($this, $method)) {
            if ($replicate === false) {
                return $this->$method();
            } else {
                return $this->$method($replicate);
            }
        }
    }

    public function toObject()
    {
        $fhir = new \StdClass();
        $fhir->entry = [];
        $fhir->resourceType = 'Bundle';
        $fhir->type = 'document';
        $fhir->entry[] = $this->getComposition();
        foreach ($this->metrics as $metric) {
            if ($entry = $this->getEntry($metric)) {
                $fhir->entry[] = $entry;
            }
        }
        return $fhir;
    }
}
