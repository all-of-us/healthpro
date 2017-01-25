<?php
namespace Pmi\Evaluation;

use Pmi\Util;

class Fhir
{
    protected $data;
    protected $schema;
    protected $patient;
    protected $version;
    protected $date;
    protected $metricUrns;

    public function __construct(array $options)
    {
        $this->data = $options['data'];
        $this->schema = $options['schema'];
        $this->patient = $options['patient'];
        $this->version = $options['version'];
        $this->metricUrns = $this->getMetricUrns();
        // Convert DateTime object to UTC timestamp
        // (can't use 'c' ISO 8601 format because that results in +00:00 instead of Z)
        $date = clone $options['datetime'];
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->date = $date->format('Y-m-d\TH:i:s\Z');
    }

    /*
     * Determines which metrics have values to represent and generates
     * URNs for each
     */
    protected function getMetricUrns()
    {
        $metrics = [];
        foreach ($this->schema->fields as $field) {
            if (preg_match('/^blood-pressure-/', $field->name) && !preg_match('/^blood-pressure-systolic/', $field->name)) {
                // only add systolic for now, will process the rest below
                continue;
            }
            if (empty($this->data->{$field->name})) {
                continue;
            }
            if (!empty($field->replicates)) {
                foreach ($this->data->{$field->name} as $i => $value) {
                    if (empty($value)) {
                        continue;
                    }
                    $metrics[] = $field->name . '-' . ($i+1); // 1-indexed
                }
            } else {
                $metrics[] = $field->name;
            }
        }
        // add bmi if height and weight are both included
        if (in_array('height', $metrics) && in_array('weight', $metrics)) {
            $metrics[] = 'bmi';
        }

        // check and rename blood pressure metrics
        $diastolic = $this->data->{'blood-pressure-diastolic'};
        foreach ($metrics as $k => $metric) {
            if (!preg_match('/^blood-pressure-systolic-(\d+)$/', $metric, $m)) {
                continue;
            }
            $index = $m[1] - 1;
            if (!empty($diastolic[$index])) {
                $metrics[$k] = 'blood-pressure-' . $m[1];
            } else {
                // remove if systolic exists but not diastolic
                unset($metrics[$k]);
            }
        }
        $metrics = array_values($metrics);

        // set urns
        $metricUrns = [];
        foreach ($metrics as $metric) {
            $uuid = Util::generateUuid();
            $metricUrns[$metric] = "urn:uuid:{$uuid}";
        }
        return $metricUrns;
    }

    protected function getComposition()
    {
        $references = [];
        foreach ($this->metricUrns as $metric => $urn) {
            $references[] = ['reference' => $urn];
        }
        return [
            'fullUrl' => 'urn:uuid:' . Util::generateUuid(),
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
                'title' => 'All of Us Intake Evaluation',
                'type' => [
                    'coding' => [[
                        'code' => "intake-exam-v{$this->version}",
                        'display' => "All of Us Intake Evaluation v{$this->version}",
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/document-type'
                    ]],
                    'text' => "All of Us Intake Evaluation v{$this->version}"
                ]
            ]
        ];
    }

    protected function simpleMetric($metric, $value, $display, $loinc, $unit)
    {
        return [
            'fullUrl' => $this->metricUrns[$metric],
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

    protected function bmi()
    {
        if (!$this->data->height || !$this->data->weight) {
            return;
        }
        $cm = $this->data->height / 100;
        $bmi = round($this->data->weight / ($cm * $cm), 1);
        return $this->simpleMetric(
            'bmi',
            $bmi,
            'Body mass index',
            '39156-5',
            'kg/m2'
        );
    }

    /*
     * Heart rate was made a replicate metric starting in 0.1.3
     * this method is backwards compatible to handle both
     */
    protected function heartrate($replicate = null)
    {
        return $this->simpleMetric(
            is_null($replicate) ? 'heart-rate' : 'heart-rate-' . $replicate,
            is_null($replicate) ? $this->data->{'heart-rate'} : $this->data->{'heart-rate'}[$replicate - 1],
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
            '56086-2',
            'cm'
        );
    }

    protected function getBpBodySite($replicate)
    {
        if (is_array($this->data->{'blood-pressure-location'})) {
            $location = $this->data->{'blood-pressure-location'}[$replicate - 1];
        } else {
            $location = $this->data->{'blood-pressure-location'};
        }
        switch ($location) {
            case 'Left arm':
                $locationSnomed = '368208006';
                $locationDisplay = 'Left arm';
                break;
            default:
                $locationSnomed = '368209003';
                $locationDisplay = 'Right arm';
                break;
        }
        return [
            'coding' => [[
                'code' => $locationSnomed,
                'display' => $locationDisplay,
                'system' => 'http://snomed.info/sct'
            ]],
            'text' => $locationDisplay
        ];
    }

    protected function getBpArmCircumference()
    {
        $armCircumference = $this->data->{'blood-pressure-arm-circumference'};
        if (!$armCircumference) {
            return false;
        }
        return [
            'code' => [
                'coding' => [[
                    'code' => 'arm-circumference',
                    'display' => 'Arm circumference',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation'
                ]],
                'text' => 'Arm circumference'
            ],
            'valueQuantity' => [
                'code' => 'cm',
                'system' => 'http://unitsofmeasure.org',
                'unit' => 'cm',
                'value' => $armCircumference
            ]
        ];
    }

    protected function getBpComponent($component, $replicate)
    {
        switch ($component) {
            case 'systolic':
                $loinc = '8480-6';
                $display = 'Systolic blood pressure';
                break;
            case 'diastolic':
                $loinc = '8462-4';
                $display = 'Diastolic blood pressure';
                break;
            default:
                throw new \Exception('Invalid blood pressure component');
        }
        return [
            'code' => [
                'coding' => [[
                    'code' => $loinc,
                    'display' => $display,
                    'system' => 'http://loinc.org'
                ]],
                'text' => $display
            ],
            'valueQuantity' => [
                'code' => 'mm[Hg]',
                'system' => 'http://unitsofmeasure.org',
                'unit' => 'mmHg',
                'value' => $this->data->{'blood-pressure-' . $component}[$replicate - 1]
            ]
        ];
    }

    protected function bloodpressure($replicate)
    {
        $components = [
            $this->getBpComponent('systolic', $replicate),
            $this->getBpComponent('diastolic', $replicate)
        ];
        if ($armCircumference = $this->getBpArmCircumference()) {
            $components[] = $armCircumference;
        }

        return [
            'fullUrl' => $this->metricUrns['blood-pressure-' . $replicate],
            'resource' => [
                'bodySite' => $this->getBpBodySite($replicate),
                'code' => [
                    'coding' => [[
                        'code' => '55284-4',
                        'display' => 'Blood pressure systolic and diastolic',
                        'system' => 'http://loinc.org'
                    ]],
                    'text' => 'Blood pressure systolic and diastolic'
                ],
                'component' => $components,
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ]
            ]
        ];
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
        foreach ($this->metricUrns as $metric => $uuid) {
            if ($entry = $this->getEntry($metric)) {
                $fhir->entry[] = $entry;
            }
        }
        return $fhir;
    }
}
