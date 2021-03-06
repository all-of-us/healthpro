<?php
namespace Pmi\Evaluation;

use Pmi\Util;

class Fhir
{
    const CURRENT_VERSION = 2;
    protected $data;
    protected $schema;
    protected $patient;
    protected $version;
    protected $date;
    protected $metricUrns;
    protected $parentRdr;
    protected $createdUser;
    protected $createdSite;
    protected $finalizedUser;
    protected $finalizedSite;
    protected $summary;

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
        $this->createdUser = $options['created_user'];
        $this->createdSite = $options['created_site'];
        $this->finalizedUser = $options['finalized_user'];
        $this->finalizedSite = $options['finalized_site'];
        $this->summary = $options['summary'];
    }

    protected static function ordinalLabel($text, $replicate)
    {
        switch ($replicate) {
            case 1:
                return 'First ' . $text;
            case 2:
                return 'Second ' . $text;
            case 3:
                return 'Third ' . $text;
            default:
                return ucfirst($text);
        }
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

        if (in_array('wheelchair', $metrics) || in_array('pregnant', $metrics)) {
            $metrics[] = 'hip-circumference-1';
            $metrics[] = 'hip-circumference-2';
            $metrics[] = 'waist-circumference-1';
            $metrics[] = 'waist-circumference-2';
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

        // add computed means
        if (in_array('blood-pressure-2', $metrics) || in_array('blood-pressure-3', $metrics)) {
            $metrics[] = 'blood-pressure-mean';
        }
        if (in_array('heart-rate-2', $metrics) || in_array('heart-rate-3', $metrics)) {
            $metrics[] = 'heart-rate-mean';
        }
        if (in_array('hip-circumference-1', $metrics) || in_array('hip-circumference-2', $metrics) || in_array('hip-circumference-3', $metrics)) {
            $metrics[] = 'hip-circumference-mean';
        }
        if (in_array('waist-circumference-1', $metrics) || in_array('waist-circumference-2', $metrics) || in_array('waist-circumference-3', $metrics)) {
            $metrics[] = 'waist-circumference-mean';
        }

        // move notes to end
        $notesIndex = array_search('notes', $metrics);
        if ($notesIndex !== false) {
            unset($metrics[$notesIndex]);
            $metrics[] = 'notes';
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
                'author' => [
                    [
                        'reference' => "Practitioner/{$this->createdUser}",
                        'extension' => [[
                            'url' => "http://terminology.pmi-ops.org/StructureDefinition/authoring-step",
                            'valueCode' => "created"
                        ]]
                    ],
                    [
                        'reference' => "Practitioner/{$this->finalizedUser}",
                        'extension' => [[
                            'url' => "http://terminology.pmi-ops.org/StructureDefinition/authoring-step",
                            'valueCode' => "finalized"
                        ]]
                    ]
                ],
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
                ],
                'extension' => [
                    [
                        'url' => "http://terminology.pmi-ops.org/StructureDefinition/authored-location",
                        'valueString' => 'Location/' . \Pmi\Security\User::SITE_PREFIX . $this->createdSite
                    ],
                    [
                        'url' => "http://terminology.pmi-ops.org/StructureDefinition/finalized-location",
                        'valueString' => 'Location/' . \Pmi\Security\User::SITE_PREFIX . $this->finalizedSite
                    ]
                ],
            ]
        ];
        if ($this->parentRdr) {
            $composition['resource']['extension'][] = [
                'url' => 'http://terminology.pmi-ops.org/StructureDefinition/amends',
                'valueReference' => [
                    'reference' => "PhysicalMeasurements/{$this->parentRdr}"
                ]
            ];
        }
        return $composition;
    }

    protected function simpleMetric($metric, $value, $display, $unit, $codes, $effectiveDateTime = null)
    {
        if (empty($effectiveDateTime)) {
            $effectiveDateTime = $this->date;
        }
        $entry = [
            'fullUrl' => $this->metricUrns[$metric],
            'resource' => [
                'code' => [
                    'coding' => $codes,
                    'text' => $display
                ],
                'effectiveDateTime' => $effectiveDateTime,
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
        if (strpos($metric, 'circumference') !== false) {
            if ($this->data->pregnant) {
                $entry['resource']['related'][] = [
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns['pregnant']
                    ]
                ];
            }
            if ($this->data->wheelchair) {
                $entry['resource']['related'][] = [
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns['wheelchair']
                    ]
                ];
            }
        }
        return $entry;
    }

    protected function valueMetric($metric, $value, $display, $codeCodes, $valueCodes)
    {
        return [
            'fullUrl' => $this->metricUrns[$metric],
            'resource' => [
                'code' => [
                    'coding' => $codeCodes,
                    'text' => $display
                ],
                'effectiveDateTime' => $this->date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ],
                'valueCodeableConcept' => [
                    'coding' => $valueCodes,
                    'text' => $value
                ]
            ]
        ];
    }

    protected function stringMetric($metric, $value, $display, $codes)
    {
        $entry = [
            'fullUrl' => $this->metricUrns[$metric],
            'resource' => [
                'code' => [
                    'coding' => $codes,
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
            $entry['resource']['valueString'] = (string)$value;
        }
        return $entry;
    }

    protected function protocolModification($metric, $replicate = null)
    {
        $codeDisplay = ucfirst(str_replace('-', ' ', $metric)) . ' protocol modifications';
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

        // Add display text for blood bank donor and EHR modifications
        if ($conceptCode === Evaluation::BLOOD_DONOR_PROTOCOL_MODIFICATION) {
            $conceptDisplay = Evaluation::BLOOD_DONOR_PROTOCOL_MODIFICATION_LABEL;
        } elseif ($conceptCode === Evaluation::EHR_PROTOCOL_MODIFICATION) {
            $conceptDisplay = Evaluation::EHR_PROTOCOL_MODIFICATION_LABEL;
        } else {
            $conceptDisplay = isset($options[$conceptCode]) ? $options[$conceptCode] : '';
        }
        // Change wheelchair concept code
        if ($conceptCode === 'wheelchair-bound') {
            $conceptCode = 'wheelchair-user';
        }
        return [
            'fullUrl' => $this->metricUrns[$urnKey],
            'resource' => [
                'code' => [
                    'coding' => [[
                        'code' => "protocol-modifications-{$metric}",
                        'display' => $codeDisplay,
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
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
                        'system' => "http://terminology.pmi-ops.org/CodeSystem/protocol-modifications-{$metric}"
                    ]],
                    'text' => ($conceptCode === 'other' && !empty($notes)) ? $notes : $conceptDisplay
                ]
            ]
        ];
    }

    protected function protocolModificationManual($metric, $replicate = null)
    {
        $codeDisplay = ucfirst(str_replace('-', ' ', $metric)) . ' protocol modifications';
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
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
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
                        'system' => "http://terminology.pmi-ops.org/CodeSystem/protocol-modifications-{$metric}"
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
            'Height',
            'cm',
            [
                [
                    'code' => '8302-2',
                    'display' => 'Body height',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'height',
                    'display' => 'Height',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('height-source')
        );
    }

    protected function weight()
    {
        return $this->simpleMetric(
            'weight',
            $this->data->weight,
            'Weight',
            'kg',
            [
                [
                    'code' => '29463-7',
                    'display' => 'Body weight',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'weight',
                    'display' => 'Weight',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('weight-source')
        );
    }

    protected function weightprepregnancy()
    {
        return $this->simpleMetric(
            'weight-prepregnancy',
            $this->data->{'weight-prepregnancy'},
            'Approximate pre-pregnancy weight',
            'kg',
            [[
                'code' => 'pre-pregnancy-weight',
                'display' => 'Approximate pre-pregnancy weight',
                'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
            ]]
        );
    }

    protected function pregnant()
    {
        if (!$this->data->pregnant) {
            return;
        }
        return $this->valueMetric(
            'pregnant',
            'Pregnant',
            'Is the participant pregnant?',
            [
                [
                    'code' => '66174-4',
                    'display' => 'Are you currently pregnant?',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'pregnancy-status',
                    'display' => 'Is the participant pregnant?',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            [
                [
                    'code' => 'LA33-6',
                    'display' => 'Yes (pregnant)',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'pregnant',
                    'display' => 'Participant is pregnant',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/pregnancy-status'
                ]
            ]
        );
    }

    protected function wheelchair()
    {
        if (!$this->data->wheelchair) {
            return;
        }
        return $this->valueMetric(
            'wheelchair',
            'Wheelchair user',
            'Is the participant a wheelchair user?',
            [[
                'code' => 'wheelchair-user-status',
                'display' => 'Is the participant a wheelchair user?',
                'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
            ]],
            [[
                'code' => 'wheelchair-user',
                'display' => 'Participant is wheelchair user',
                'system' => 'http://terminology.pmi-ops.org/CodeSystem/wheelchair-user-status'
            ]]
        );
    }

    protected function bmi()
    {
        if (!$this->data->height || !$this->data->weight) {
            return;
        }
        $cm = $this->data->height / 100;
        $bmi = round($this->data->weight / ($cm * $cm), 1);
        $entry = $this->simpleMetric(
            'bmi',
            $bmi,
            'Computed body mass index',
            'kg/m2',
            [
                [
                    'code' => '39156-5',
                    'display' => 'Body mass index',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'bmi',
                    'display' => 'Computed body mass index',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('weight-source')
        );
        $related = [];
        foreach (['height-protocol-modification', 'weight-protocol-modification'] as $metric) {
            if (array_key_exists($metric, $this->metricUrns)) {
                $related[] = [
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns[$metric]
                    ]
                ];
            }
        }
        if ($related) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    protected function heartrate($replicate = null)
    {
        $entry = $this->simpleMetric(
            'heart-rate-' . $replicate,
            $this->data->{'heart-rate'}[$replicate - 1],
            self::ordinalLabel('heart rate', $replicate),
            '/min',
            [
                [
                    'code' => '8867-4',
                    'display' => 'Heart rate',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'heart-rate-' . $replicate,
                    'display' => self::ordinalLabel('heart rate', $replicate),
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
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
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
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

        return $entry;
    }

    protected function hipcircumference($replicate)
    {
        return $this->simpleMetric(
            'hip-circumference-' . $replicate,
            $this->data->{'hip-circumference'}[$replicate - 1],
            self::ordinalLabel('hip circumference', $replicate),
            'cm',
            [
                [
                    'code' => '62409-8',
                    'display' => 'Hip circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'hip-circumference-' . $replicate,
                    'display' => self::ordinalLabel('hip circumference', $replicate),
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('hip-circumference-source')
        );
    }

    protected function waistcircumference($replicate)
    {
        $entry = $this->simpleMetric(
            'waist-circumference-' . $replicate,
            $this->data->{'waist-circumference'}[$replicate - 1],
            self::ordinalLabel('waist circumference', $replicate),
            'cm',
            [
                [
                    'code' => '56086-2',
                    'display' => 'Waist circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'waist-circumference-' . $replicate,
                    'display' => self::ordinalLabel('waist circumference', $replicate),
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('waist-circumference-source')
        );
        if (isset($this->data->{'waist-circumference-location'})) {
            $entry['resource']['bodySite'] = $this->getWaistCircumferenceBodySite();
        }
        return $entry;
    }

    protected function getBpBodySite($location)
    {
        switch ($location) {
            case 'Left arm':
                $locationSnomed = '368208006';
                $locationPmi = 'left-arm';
                $locationDisplay = 'Left arm';
                break;
            case 'Right arm':
                $locationSnomed = '368209003';
                $locationPmi = 'right-arm';
                $locationDisplay = 'Right arm';
                break;
            default:
                return null;
        }
        return [
            'coding' => [
                [
                    'code' => $locationSnomed,
                    'display' => $locationDisplay,
                    'system' => 'http://snomed.info/sct'
                ],
                [
                    'code' => $locationPmi,
                    'display' => $locationDisplay,
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/blood-pressure-location'
                ]
            ],
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

    /*
     * $replicate can be integer for replicate number OR 'mean'
     */
    protected function getBpComponent($component, $replicate)
    {
        switch ($component) {
            case 'systolic':
                $loincCode = '8480-6';
                $loincDisplay = 'Systolic blood pressure';
                $pmiCode = 'blood-pressure-systolic-' . $replicate;
                if ($replicate === 'mean') {
                    $pmiDisplay = 'Computed systolic blood pressure, mean of 2nd and 3rd measures';
                } else {
                    $pmiDisplay = self::ordinalLabel('systolic blood pressure', $replicate);
                }
                break;
            case 'diastolic':
                $loincCode = '8462-4';
                $loincDisplay = 'Diastolic blood pressure';
                $pmiCode = 'blood-pressure-diastolic-' . $replicate;
                if ($replicate === 'mean') {
                    $pmiDisplay = 'Computed diastolic blood pressure, mean of 2nd and 3rd measures';
                } else {
                    $pmiDisplay = self::ordinalLabel('diastolic blood pressure', $replicate);
                }
                break;
            default:
                throw new \Exception('Invalid blood pressure component');
        }
        if ($replicate === 'mean') {
            if (isset($this->summary['bloodpressure']) && isset($this->summary['bloodpressure'][$component])) {
                $value = $this->summary['bloodpressure'][$component];
            } else {
                $value = null;
            }
        } else {
            $value = $this->data->{'blood-pressure-' . $component}[$replicate - 1];
        }
        if (is_null($value)) {
            return null;
        }
        return [
            'code' => [
                'coding' => [
                    [
                        'code' => $loincCode,
                        'display' => $loincDisplay,
                        'system' => 'http://loinc.org'
                    ],
                    [
                        'code' => $pmiCode,
                        'display' => $pmiDisplay,
                        'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                    ]
                ],
                'text' => $pmiDisplay
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
        $components = array_values(array_filter($components)); // remove components that return null and rearrange index keys
        $entry = [
            'fullUrl' => $this->metricUrns['blood-pressure-' . $replicate],
            'resource' => [
                'code' => [
                    'coding' => [
                        [
                            'code' => '55284-4',
                            'display' => 'Blood pressure systolic and diastolic',
                            'system' => 'http://loinc.org'
                        ],
                        [
                            'code' => 'blood-pressure-' . $replicate,
                            'display' => self::ordinalLabel('blood pressure systolic and diastolic', $replicate),
                            'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                        ]
                    ],
                    'text' => self::ordinalLabel('blood pressure systolic and diastolic', $replicate)
                ],
                'component' => $components,
                'effectiveDateTime' => $this->getEffectiveDateTime('blood-pressure-source', $replicate),
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->patient}"
                ]
            ]
        ];
        if (isset($this->data->{'blood-pressure-location'})) {
            $entry['resource']['bodySite'] = $this->getBpBodySite($this->data->{'blood-pressure-location'});
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

    protected function meanProtocolModifications($replicates, $modificationMetric, $modificationMetricManual = null)
    {
        $related = [];
        foreach ($replicates as $replicate) {
            $metric = $modificationMetric . $replicate;
            if (array_key_exists($metric, $this->metricUrns)) {
                $related[] = [
                    'type' => 'qualified-by',
                    'target' => [
                        'reference' => $this->metricUrns[$metric]
                    ]
                ];
            }
            if ($modificationMetricManual) {
                $metricManual = $modificationMetricManual . $replicate;
                if (array_key_exists($metricManual, $this->metricUrns)) {
                    $related[] = [
                        'type' => 'qualified-by',
                        'target' => [
                            'reference' => $this->metricUrns[$metricManual]
                        ]
                    ];
                }
            }
        }
        return $related;
    }

    protected function bloodpressuremean()
    {
        $components = [
            $this->getBpComponent('systolic', 'mean'),
            $this->getBpComponent('diastolic', 'mean')
        ];
        $components = array_filter($components); // remove components that return null
        $entry = [
            'fullUrl' => $this->metricUrns['blood-pressure-mean'],
            'resource' => [
                'code' => [
                    'coding' => [
                        [
                            'code' => '55284-4',
                            'display' => 'Blood pressure systolic and diastolic',
                            'system' => 'http://loinc.org'
                        ],
                        [
                            'code' => 'blood-pressure-mean',
                            'display' => 'Computed blood pressure systolic and diastolic, mean of 2nd and 3rd measures',
                            'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                        ]
                    ],
                    'text' => 'Computed blood pressure systolic and diastolic, mean of 2nd and 3rd measures'
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
            $entry['resource']['bodySite'] = $this->getBpBodySite($this->data->{'blood-pressure-location'});
        }
        if ($related = $this->meanProtocolModifications([2,3], 'blood-pressure-protocol-modification-', 'manual-blood-pressure-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    protected function heartratemean()
    {
        $entry = $this->simpleMetric(
            'heart-rate-mean',
            isset($this->summary['heartrate']) ? $this->summary['heartrate'] : null,
            'Computed heart rate, mean of 2nd and 3rd measures',
            '/min',
            [
                [
                    'code' => '8867-4',
                    'display' => 'Heart rate',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'heart-rate-mean',
                    'display' => 'Computed heart rate, mean of 2nd and 3rd measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if ($this->data->{'irregular-heart-rate'}[1] && $this->data->{'irregular-heart-rate'}[2]) {
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
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]],
                'text' => 'Heart rhythm status'
            ],
            'valueCodeableConcept' => $concept
        ]];
        if ($related = $this->meanProtocolModifications([2,3], 'blood-pressure-protocol-modification-', 'manual-heart-rate-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    protected function hipcircumferencemean()
    {
        $entry = $this->simpleMetric(
            'hip-circumference-mean',
            !empty($this->summary['hip']['cm']) ? $this->summary['hip']['cm'] : null,
            'Computed hip circumference, mean of closest two measures',
            'cm',
            [
                [
                    'code' => '62409-8',
                    'display' => 'Hip circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'hip-circumference-mean',
                    'display' => 'Computed hip circumference, mean of closest two measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if ($related = $this->meanProtocolModifications([1,2,3], 'hip-circumference-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    protected function waistcircumferencemean()
    {
        $entry = $this->simpleMetric(
            'waist-circumference-mean',
            !empty($this->summary['waist']['cm']) ? $this->summary['waist']['cm'] : null,
            'Computed waist circumference, mean of closest two measures',
            'cm',
            [
                [
                    'code' => '56086-2',
                    'display' => 'Waist circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'waist-circumference-mean',
                    'display' => 'Computed waist circumference, mean of closest two measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if (isset($this->data->{'waist-circumference-location'})) {
            $entry['resource']['bodySite'] = $this->getWaistCircumferenceBodySite();
        }
        if ($related = $this->meanProtocolModifications([1,2,3], 'waist-circumference-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    protected function notes()
    {
        if (!$this->data->notes) {
            return;
        }
        return $this->stringMetric(
            'notes',
            $this->data->notes,
            'Additional notes',
            [[
                'code' =>  'notes',
                'display' =>  'Additional notes',
                'system' =>  'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
            ]]
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

    protected function getEffectiveDateTime($field, $replicate = 1)
    {
        if (isset($this->data->{$field}) && $this->data->{$field} === 'ehr' && !empty($this->data->{$field . '-ehr-date'}) && $replicate == 1) {
            if (is_string($this->data->{$field . '-ehr-date'})) {
                $date = new \DateTime($this->data->{$field . '-ehr-date'});
                return $date->format('Y-m-d\TH:i:s\Z');
            }
            return $this->data->{$field . '-ehr-date'}->format('Y-m-d\TH:i:s\Z');
        }
        return $this->date;
    }
}
