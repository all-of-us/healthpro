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
    protected $parentRdr;

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
        $this->parentRdr = $options['parent_rdr'];
    }

    /*
     * Determines which metrics have values to represent and generates
     * URNs for each
     */
    protected function getMetricUrns()
    {
        $metrics = [];
        foreach ($this->schema->fields as $field) {
            if (preg_match('/^blood-pressure-/', $field->name)) {
                if (!preg_match('/^blood-pressure-(systolic|protocol-modification)/', $field->name)) {
                    // only add systolic and modifications for now, will process the rest below
                    continue;
                }
            }
            if (preg_match('/protocol-modification/', $field->name)) {
                $isModification = true;
            } else {
                $isModification = false;
                switch ($field->name) {
                    case 'blood-pressure-systolic':
                    case 'heart-rate':
                        $modification = 'blood-pressure-protocol-modification';
                        break;
                    default:
                        $modification = $field->name . '-protocol-modification';
                }
            }

            if (!empty($field->replicates)) {
                if (empty($this->data->{$field->name}) && empty($this->data->{$modification})) {
                    continue;
                }
                foreach ($this->data->{$field->name} as $i => $value) {
                    if (empty($value) && empty($this->data->{$modification}[$i])) {
                        continue;
                    }
                    $metrics[] = $field->name . '-' . ($i+1); // 1-indexed
                }
            } else {
                if (empty($this->data->{$field->name}) && empty($this->data->{$modification})) {
                    continue;
                }
                $metrics[] = $field->name;
            }
        }
        // add bmi if height and weight are both included
        if (in_array('height', $metrics) && in_array('weight', $metrics)) {
            $metrics[] = 'bmi';
        }

        // check and rename blood pressure metrics
        $diastolic = $this->data->{'blood-pressure-diastolic'};
        $bpModification = $this->data->{'blood-pressure-protocol-modification'};
        foreach ($metrics as $k => $metric) {
            if (!preg_match('/^blood-pressure-systolic-(\d+)$/', $metric, $m)) {
                continue;
            }
            $index = $m[1] - 1;
            if (!empty($diastolic[$index]) || !empty($bpModification[$index])) {
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
        $composition = [
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
        if ($this->parentRdr) {
            $composition['resource']['extension'] = [[
                'url' => 'http://terminology.pmi-ops.org/StructureDefinition/amends',
                'valueReference' => [
                    'reference' => "PhysicalMeasurements/{$this->parentRdr}"
                ]
            ]];
        }
        return $composition;
    }

    protected function simpleMetric($metric, $value, $display, $code, $unit, $system = 'http://loinc.org')
    {
        $entry = [
            'fullUrl' => $this->metricUrns[$metric],
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => $code,
                        'display' => $display,
                        'system' => $system
                    ]],
                    'text' => $display
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ]
            ]
        ];
        if (!is_null($value)) {
            $entry['resource']['valueQuantity'] = [
                'code' => $unit,
                'system' => 'http://unitsofmeasure.org',
                'unit' => $unit,
                'value' => $value
            ];
        }
        if (preg_match('/-(\d+)$/', $metric)) {
            $modificationMetric = preg_replace('/-(\d+)$/', '-protocol-modification-$1', $metric);
        } else {
            $modificationMetric = $metric . '-protocol-modification';
        }
        if (array_key_exists($modificationMetric, $this->metricUrns)) {
            $entry['resource']['related'] = [[
                'type' => 'qualified-by',
                'target' => [
                    'reference' => $this->metricUrns[$modificationMetric]
                ]
            ]];
        }
        return $entry;
    }

    protected function valueMetric($metric, $value, $display, $codeCode, $valueCode, $codeSystem = 'http://loinc.org', $valueSystem = 'http://loinc.org')
    {
        return [
            'fullUrl' => $this->metricUrns[$metric],
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => $codeCode,
                        'display' => $display,
                        'system' => $codeSystem
                    ]],
                    'text' => $display
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueCodeableConcept' => [
                    'coding' => [[
                        'code' => $valueCode,
                        'display' => $value,
                        'system' => $valueSystem
                    ]],
                    'text' => $value
                ]
            ]
        ];
    }

    protected function protocolModification($metric, $replicate = null)
    {
        $codeDisplay = "Protocol modifications: " . ucfirst(str_replace('-', ' ', $metric));
        if (is_null($replicate)) {
            $conceptCode = $this->data->{"{$metric}-protocol-modification"};
            $urnKey = $metric . '-protocol-modification';
            $notes = isset($this->data->{"{$metric}-protocol-modification-notes"}) ? $this->data->{"{$metric}-protocol-modification-notes"} : '';
        } else {
            $conceptCode = $this->data->{"{$metric}-protocol-modification"}[$replicate-1];
            $urnKey = $metric . '-protocol-modification-' . $replicate;
            $notes = isset($this->data->{"{$metric}-protocol-modification-notes"}[$replicate-1]) ? $this->data->{"{$metric}-protocol-modification-notes"}[$replicate-1] : '';
        }
        $options = array_flip((array)$this->schema->fields["{$metric}-protocol-modification"]->options);
        $conceptDisplay = isset($options[$conceptCode]) ? $options[$conceptCode] : '';
        return [
            'fullUrl' => $this->metricUrns[$urnKey],
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => "protocol-modifications-{$metric}",
                        'display' => $codeDisplay,
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation'
                    ]],
                    'text' => $codeDisplay
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueCodeableConcept' => [
                    'coding' => [[
                        'code' => $conceptCode,
                        'display' => $conceptDisplay,
                        'system' => "http://terminology.pmi-ops.org/CodeSystem/{$conceptCode}"
                    ]],
                    'text' => ($conceptCode === 'other' && !empty($notes)) ? $notes : $conceptDisplay
                ]
            ]
        ];
    }

    protected function protocolModificationManual($metric, $replicate = null)
    {
        $codeDisplay = "Protocol modifications: " . ucfirst(str_replace('-', ' ', $metric));
        $conceptCode = 'manual-'.$metric;
        if (is_null($replicate)) {
            $urnKey = $conceptCode;
        } else {
            $urnKey = $conceptCode .'-'. $replicate;
        }
        $conceptDisplay = ucfirst(str_replace('-', ' ', $conceptCode));
        return [
            'fullUrl' => $this->metricUrns[$urnKey],
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => "protocol-modifications-{$metric}",
                        'display' => $codeDisplay,
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation'
                    ]],
                    'text' => $codeDisplay
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueCodeableConcept' => [
                    'coding' => [[
                        'code' => $conceptCode,
                        'display' => $conceptDisplay,
                        'system' => "http://terminology.pmi-ops.org/CodeSystem/{$conceptCode}"
                    ]],
                    'text' => $conceptDisplay
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

    protected function weightprepregnancy()
    {
        return $this->simpleMetric(
            'weight-prepregnancy',
            $this->data->{'weight-prepregnancy'},
            'Approximate pre-pregnancy weight',
            'pre-pregnancy-weight',
            'kg',
            'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation'
        );
    }

    protected function pregnant()
    {
        if (!$this->data->pregnant) {
            return;
        }
        return $this->valueMetric(
            'pregnant',
            'Yes (pregnant)',
            'Are you currently pregnant?',
            '66174-4',
            'LA33-6'
        );
    }

    protected function wheelchair()
    {
        if (!$this->data->wheelchair) {
            return;
        }
        return $this->valueMetric(
            'wheelchair',
            'Wheelchair bound',
            'Are you wheelchair-bound?',
            'wheelchair-bound-status',
            'wheelchair-bound',
            'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation',
            'http://terminology.pmi-ops.org/CodeSystem/wheelchair-bound-status'
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
        $entry = $this->simpleMetric(
            is_null($replicate) ? 'heart-rate' : 'heart-rate-' . $replicate,
            is_null($replicate) ? $this->data->{'heart-rate'} : $this->data->{'heart-rate'}[$replicate - 1],
            'Heart rate',
            '8867-4',
            '/min'
        );
        if ($replicate) {
            if ($this->data->{'irregular-heart-rate'}[$replicate - 1]) {
                $concept = [
                    'coding' => [[
                        'code' => 'irregularity-detected',
                        'display' => 'Irregularity detected',
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/heart-rhythm-status'
                    ]],
                    'text' => 'Irregularity detected'
                ];
            } else {
                $concept = [
                    'coding' => [[
                        'code' => 'no-irregularity-detected',
                        'display' => 'No irregularity detected',
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/heart-rhythm-status'
                    ]],
                    'text' => 'No irregularity detected'
                ];
            }
            $entry['resource']['component'] = [[
                'code' => [
                    'coding' => [[
                        'code' => 'heart-rhythm-status',
                        'display' => 'Heart rhythm status',
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-evaluation'
                    ]],
                    'text' => 'Heart rhythm status'
                ],
                'valueCodeableConcept' => $concept
            ]];
            $modificationMetric = 'blood-pressure-protocol-modification-' . $replicate;
            $modificationMetricManual = 'manual-heart-rate-' . $replicate;
            if (array_key_exists($modificationMetric, $this->metricUrns)) {
                $entry['resource']['related'] = [[
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns[$modificationMetric]
                    ]
                ]];
            }
            if (array_key_exists($modificationMetricManual, $this->metricUrns)) {
                $qualifiedBy = [
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns[$modificationMetricManual]
                    ]
                ];
                if (empty($entry['resource']['related'])) {
                    $entry['resource']['related'] = [$qualifiedBy];
                } else {
                    $entry['resource']['related'][] = $qualifiedBy;
                }
            }
        }

        return $entry;
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
        $entry = $this->simpleMetric(
            'waist-circumference-' . $replicate,
            $this->data->{'waist-circumference'}[$replicate - 1],
            'Waist circumference',
            '56086-2',
            'cm'
        );
        if (isset($this->data->{'waist-circumference-location'})) {
            $entry['resource']['bodySite'] = $this->getWaistCircumferenceBodySite();
        }
        return $entry;  
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

    protected function getWaistCircumferenceBodySite()
    {
        $locationSnomed = $this->data->{'waist-circumference-location'};
        $locationDisplay = ucfirst(str_replace('-', ' ', $locationSnomed));
        return [
            'coding' => [[
                'code' => $locationSnomed ,
                'display' => $locationDisplay ,
                'system' => 'http://terminology.pmi-ops.org/CodeSystem/waist-circumference-location'
            ]],
            'text' => $locationDisplay
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
        $value = $this->data->{'blood-pressure-' . $component}[$replicate - 1];
        if (is_null($value)) {
            return null;
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
                'value' => $value
            ]
        ];
    }

    protected function bloodpressure($replicate)
    {
        $components = [
            $this->getBpComponent('systolic', $replicate),
            $this->getBpComponent('diastolic', $replicate)
        ];
        $components = array_filter($components); // remove components that return null
        $entry = [
            'fullUrl' => $this->metricUrns['blood-pressure-' . $replicate],
            'resource' => [
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
        if (isset($this->data->{'blood-pressure-location'})) {
            $entry['resource']['bodySite'] = $this->getBpBodySite($replicate);
        }
        $modificationMetric = 'blood-pressure-protocol-modification-' . $replicate;
        $modificationMetricManual = 'manual-blood-pressure-' . $replicate;
        if (array_key_exists($modificationMetric, $this->metricUrns)) {
            $entry['resource']['related'] = [[
                'type' => 'qualified-by',
                'target' => [
                    'reference' => $this->metricUrns[$modificationMetric]
                ]
            ]];
        }
        if (array_key_exists($modificationMetricManual, $this->metricUrns)) {
            $qualifiedBy = [
                'type' => 'qualified-by',
                'target' => [
                    'reference' => $this->metricUrns[$modificationMetricManual]
                ]
            ];
            if (empty($entry['resource']['related'])) {
                $entry['resource']['related'] = [$qualifiedBy];
            } else {
                $entry['resource']['related'][] = $qualifiedBy;
            }
        }
        return $entry;
    }

    protected function bloodpressureprotocolmodification($replicate)
    {
        return $this->protocolModification('blood-pressure', $replicate);
    }

    protected function manualbloodpressure($replicate)
    {
        return $this->protocolModificationManual('blood-pressure', $replicate);
    }

    protected function manualheartrate($replicate)
    {
        return $this->protocolModificationManual('heart-rate', $replicate);
    }

    protected function hipcircumferenceprotocolmodification($replicate)
    {
        return $this->protocolModification('hip-circumference', $replicate);
    }

    protected function waistcircumferenceprotocolmodification($replicate)
    {
        return $this->protocolModification('waist-circumference', $replicate);
    }

    protected function heightprotocolmodification()
    {
        return $this->protocolModification('height');
    }

    protected function weightprotocolmodification()
    {
        return $this->protocolModification('weight');
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
        foreach ($this->metricUrns as $metric => $uuid) {
            if ($entry = $this->getEntry($metric)) {
                $fhir->entry[] = $entry;
            } else {
                unset($this->metricUrns[$metric]);
            }
        }
        array_unshift($fhir->entry, $this->getComposition());
        return $fhir;
    }
}
