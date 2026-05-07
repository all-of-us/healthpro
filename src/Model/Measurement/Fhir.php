<?php

namespace App\Model\Measurement;

use App\Entity\Measurement;
use App\Helper\Util;
use DateTimeZone;
use stdClass;

class Fhir
{
    public const CURRENT_VERSION = 2;

    /** @var object */
    protected $data;

    /** @var stdClass */
    protected $schema;

    /** @var string */
    protected $patient;

    /** @var string */
    protected $version;

    /** @var string */
    protected $date;

    /** @var array<string, string> */
    protected $metricUrns;

    /** @var string|null */
    protected $parentRdr;

    /** @var string */
    protected $createdUser;

    /** @var string */
    protected $createdSite;

    /** @var string|null */
    protected $finalizedUser;

    /** @var string|null */
    protected $finalizedSite;

    /** @var array<string, mixed> */
    protected $summary;

    /**
     * @param array{
     *     data: object,
     *     schema: stdClass,
     *     patient: string,
     *     version: string,
     *     datetime: \DateTimeInterface,
     *     parent_rdr: string|null,
     *     created_user: string,
     *     created_site: string,
     *     finalized_user: string|null,
     *     finalized_site: string|null,
     *     summary: array<string, mixed>
     * } $options
     */
    public function __construct(array $options)
    {
        $this->data = $options['data'];
        $this->schema = $options['schema'];
        $this->patient = $options['patient'];
        $this->version = $options['version'];
        // Convert DateTime object to UTC timestamp
        // (can't use 'c' ISO 8601 format because that results in +00:00 instead of Z)
        $date = $options['datetime'];
        if ($date instanceof \DateTimeImmutable) {
            $date = $date->setTimezone(new DateTimeZone('UTC'));
        } else {
            $date = clone $date;
            $date->setTimezone(new DateTimeZone('UTC'));
        }
        $this->date = $date->format('Y-m-d\TH:i:s\Z');
        $this->parentRdr = $options['parent_rdr'];
        $this->createdUser = $options['created_user'];
        $this->createdSite = $options['created_site'];
        $this->finalizedUser = $options['finalized_user'];
        $this->finalizedSite = $options['finalized_site'];
        $this->summary = $options['summary'];
        $this->metricUrns = $this->getMetricUrns();
    }

    public function toObject(): stdClass
    {
        $fhir = new StdClass();
        $fhir->entry = [];
        $fhir->resourceType = 'Bundle';
        $fhir->type = 'document';
        foreach (array_keys($this->metricUrns) as $metric) {
            if ($entry = $this->getEntry($metric)) {
                $fhir->entry[] = $entry;
            } else {
                unset($this->metricUrns[$metric]);
            }
        }
        array_unshift($fhir->entry, $this->getComposition());
        return $fhir;
    }

    protected static function ordinalLabel(string $text, int $replicate): string
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

    /**
     * @return array<string, string>
     */
    protected function getMetricUrns(): array
    {
        $metrics = [];
        /** @var array<int, stdClass> $fields */
        $fields = $this->schema->fields;
        /** @var stdClass $field */
        foreach ($fields as $field) {
            if (preg_match('/^blood-pressure-/', $field->name)) {
                if (!preg_match('/^blood-pressure-(systolic|protocol-modification)/', $field->name)) {
                    // only add systolic and modifications for now, will process the rest below
                    continue;
                }
            }
            $modification = '';
            if (!preg_match('/protocol-modification/', $field->name)) {
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
                    $metrics[] = $field->name . '-' . ($i + 1); // 1-indexed
                }
            } else {
                if (empty($this->data->{$field->name}) && empty($this->data->{$modification})) {
                    continue;
                }
                $metrics[] = $field->name;
            }
        }

        if ($this->isPediatricForm()) {
            if (in_array('wheelchair', $metrics)) {
                if (isset($this->schema->fields['hip-circumference'])) {
                    $metrics[] = 'hip-circumference-1';
                    $metrics[] = 'hip-circumference-2';
                }
                if (isset($this->schema->fields['waist-circumference'])) {
                    $metrics[] = 'waist-circumference-1';
                    $metrics[] = 'waist-circumference-2';
                }
                if (isset($this->schema->fields['head-circumference'])) {
                    $metrics[] = 'head-circumference-1';
                    $metrics[] = 'head-circumference-2';
                }
            }
        } else {
            if (in_array('wheelchair', $metrics) || in_array('pregnant', $metrics)) {
                $metrics[] = 'hip-circumference-1';
                $metrics[] = 'hip-circumference-2';
                $metrics[] = 'waist-circumference-1';
                $metrics[] = 'waist-circumference-2';
            }
        }

        // add bmi if height and weight are both included
        if (!$this->isPediatricForm() && in_array('height', $metrics) && in_array('weight', $metrics)) {
            $metrics[] = 'bmi';
        }

        // check and rename blood pressure metrics
        if (isset($this->data->{'blood-pressure-diastolic'})) {
            $diastolic = $this->data->{'blood-pressure-diastolic'};
            $bpModification = $this->data->{'blood-pressure-protocol-modification'};
            foreach ($metrics as $k => $metric) {
                if (!preg_match('/^blood-pressure-systolic-(\d+)$/', $metric, $m)) {
                    continue;
                }
                $index = (int) $m[1] - 1;
                if (!empty($diastolic[$index]) || !empty($bpModification[$index])) {
                    $metrics[$k] = 'blood-pressure-' . $m[1];
                } else {
                    // remove if systolic exists but not diastolic
                    unset($metrics[$k]);
                }
            }
        }

        $summaryKeys = $this->summary ? array_keys($this->summary) : [];
        // add computed means
        if (in_array('weight-1', $metrics) || in_array('weight-2', $metrics) || in_array('weight-3', $metrics)) {
            $metrics[] = 'weight-mean';
            $weightGrowthMetrics = ['growth-percentile-weight-for-age', 'growth-percentile-weight-for-age-male', 'growth-percentile-weight-for-age-female'];
            foreach ($weightGrowthMetrics as $weightGrowthMetric) {
                if (in_array($weightGrowthMetric, $summaryKeys)) {
                    $metrics[] = $weightGrowthMetric;
                }
            }
        }
        if (in_array('height-1', $metrics) || in_array('height-2', $metrics) || in_array('height-3', $metrics)) {
            $metrics[] = 'height-mean';
            $heightGrowthMetrics = ['growth-percentile-height-for-age', 'growth-percentile-height-for-age-male', 'growth-percentile-height-for-age-female'];
            foreach ($heightGrowthMetrics as $heightGrowthMetric) {
                if (in_array($heightGrowthMetric, $summaryKeys)) {
                    $metrics[] = $heightGrowthMetric;
                }
            }
        }
        if (in_array('weight-mean', $metrics) && in_array('height-mean', $metrics)) {
            $weightLengthGrowthMetrics = ['growth-percentile-weight-for-length', 'growth-percentile-weight-for-length-male', 'growth-percentile-weight-for-length-female'];
            foreach ($weightLengthGrowthMetrics as $weightLengthGrowthMetric) {
                if (in_array($weightLengthGrowthMetric, $summaryKeys)) {
                    $metrics[] = $weightLengthGrowthMetric;
                }
            }
            if ($this->schema->displayBmi) {
                $bmiGrowthMetrics = ['growth-percentile-bmi-for-age', 'growth-percentile-bmi-for-age-male', 'growth-percentile-bmi-for-age-female'];
                foreach ($bmiGrowthMetrics as $bmiGrowthMetric) {
                    if (in_array($bmiGrowthMetric, $summaryKeys)) {
                        $metrics[] = $bmiGrowthMetric;
                    }
                }
                $metrics[] = 'bmi';
            }
        }
        if ($this->isPediatricForm()) {
            if (in_array('blood-pressure-1', $metrics) || in_array('blood-pressure-2', $metrics) || in_array('blood-pressure-3', $metrics)) {
                $metrics[] = 'blood-pressure-mean';
            }
            if (in_array('heart-rate-1', $metrics) || in_array('heart-rate-2', $metrics) || in_array('heart-rate-3', $metrics)) {
                $metrics[] = 'heart-rate-mean';
            }
        } else {
            if (in_array('blood-pressure-2', $metrics) || in_array('blood-pressure-3', $metrics)) {
                $metrics[] = 'blood-pressure-mean';
            }
            if (in_array('heart-rate-2', $metrics) || in_array('heart-rate-3', $metrics)) {
                $metrics[] = 'heart-rate-mean';
            }
        }
        if (in_array('hip-circumference-1', $metrics) || in_array('hip-circumference-2', $metrics) || in_array('hip-circumference-3', $metrics)) {
            $metrics[] = 'hip-circumference-mean';
        }
        if (in_array('waist-circumference-1', $metrics) || in_array('waist-circumference-2', $metrics) || in_array('waist-circumference-3', $metrics)) {
            $metrics[] = 'waist-circumference-mean';
        }
        if (in_array('head-circumference-1', $metrics) || in_array('head-circumference-2', $metrics) || in_array('head-circumference-3', $metrics)) {
            $metrics[] = 'head-circumference-mean';
            $headCircumferenceGrowthMetrics = ['growth-percentile-head-circumference-for-age', 'growth-percentile-head-circumference-for-age-male', 'growth-percentile-head-circumference-for-age-female'];
            foreach ($headCircumferenceGrowthMetrics as $headCircumferenceGrowthMetric) {
                if (in_array($headCircumferenceGrowthMetric, $summaryKeys)) {
                    $metrics[] = $headCircumferenceGrowthMetric;
                }
            }
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

    /**
     * @return array<string, mixed>
     */
    protected function getComposition(): array
    {
        $references = [];
        foreach (array_values($this->metricUrns) as $urn) {
            $references[] = ['reference' => $urn];
        }
        $composition = [
            'fullUrl' => 'urn:uuid:' . Util::generateUuid(),
            'resource' => [
                'author' => [
                    [
                        'reference' => "Practitioner/{$this->createdUser}",
                        'extension' => [[
                            'url' => 'http://terminology.pmi-ops.org/StructureDefinition/authoring-step',
                            'valueCode' => 'created'
                        ]]
                    ],
                    [
                        'reference' => "Practitioner/{$this->finalizedUser}",
                        'extension' => [[
                            'url' => 'http://terminology.pmi-ops.org/StructureDefinition/authoring-step',
                            'valueCode' => 'finalized'
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
                        'url' => 'http://terminology.pmi-ops.org/StructureDefinition/authored-location',
                        'valueString' => 'Location/' . $this->createdSite
                    ],
                    [
                        'url' => 'http://terminology.pmi-ops.org/StructureDefinition/finalized-location',
                        'valueString' => 'Location/' . $this->finalizedSite
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

    /**
     * @param array<int, array<string, string>> $codes
     *
     * @return array<string, mixed>
     */
    protected function simpleMetric(string $metric, mixed $value, string $display, string $unit, array $codes, ?string $effectiveDateTime = null): array
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
            if (!empty($this->data->pregnant)) {
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

    /**
     * @param array<int, array<string, string>> $codeCodes
     * @param array<int, array<string, string>> $valueCodes
     *
     * @return array<string, mixed>
     */
    protected function valueMetric(string $metric, mixed $value, string $display, array $codeCodes, array $valueCodes): array
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

    /**
     * @param array<int, array<string, string>> $codes
     *
     * @return array<string, mixed>
     */
    protected function stringMetric(string $metric, mixed $value, string $display, array $codes): array
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
            $entry['resource']['valueString'] = (string) $value;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function protocolModification(string $metric, ?int $replicate = null): array
    {
        $codeDisplay = ucfirst(str_replace('-', ' ', $metric)) . ' protocol modifications';
        if (is_null($replicate)) {
            $conceptCode = $this->data->{"{$metric}-protocol-modification"};
            $urnKey = $metric . '-protocol-modification';
            $notes = isset($this->data->{"{$metric}-protocol-modification-notes"}) ? $this->data->{"{$metric}-protocol-modification-notes"} : '';
        } else {
            $conceptCode = $this->data->{"{$metric}-protocol-modification"}[$replicate - 1];
            $urnKey = $metric . '-protocol-modification-' . $replicate;
            $notes = isset($this->data->{"{$metric}-protocol-modification-notes"}[$replicate - 1]) ? $this->data->{"{$metric}-protocol-modification-notes"}[$replicate - 1] : '';
        }
        $options = array_flip((array) $this->schema->fields["{$metric}-protocol-modification"]->options);

        // Add display text for blood bank donor and EHR modifications
        if ($conceptCode === Measurement::BLOOD_DONOR_PROTOCOL_MODIFICATION) {
            $conceptDisplay = Measurement::BLOOD_DONOR_PROTOCOL_MODIFICATION_LABEL;
        } elseif ($conceptCode === Measurement::EHR_PROTOCOL_MODIFICATION) {
            $conceptDisplay = Measurement::EHR_PROTOCOL_MODIFICATION_LABEL;
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

    /**
     * @return array<string, mixed>
     */
    protected function protocolModificationManual(string $metric, ?int $replicate = null): array
    {
        $codeDisplay = ucfirst(str_replace('-', ' ', $metric)) . ' protocol modifications';
        $conceptCode = 'manual-' . $metric;
        if (is_null($replicate)) {
            $urnKey = $conceptCode;
        } else {
            $urnKey = $conceptCode . '-' . $replicate;
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

    /**
     * @return array<string, mixed>
     */
    protected function height(?int $replicate = null): array
    {
        $metricName = $replicate ? 'height-' . $replicate : 'height';
        $value = $replicate ? $this->data->height[$replicate - 1] : $this->data->height;
        $label = $replicate ? self::ordinalLabel('height', $replicate) : 'Height';

        $entry = $this->simpleMetric(
            $metricName,
            $value,
            $label,
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

        if (isset($this->data->{'height-or-length'})) {
            $entry['resource']['component'] = [$this->getPediatricComponent('height-or-length')];
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function weight(?int $replicate = null): array
    {
        $metricName = $replicate ? 'weight-' . $replicate : 'weight';
        $value = $replicate ? $this->data->weight[$replicate - 1] : $this->data->weight;
        $label = $replicate ? self::ordinalLabel('weight', $replicate) : 'Weight';

        return $this->simpleMetric(
            $metricName,
            $value,
            $label,
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

    /**
     * @return array<string, mixed>
     */
    protected function weightprepregnancy(): array
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

    /**
     * @return array<string, mixed>|null
     */
    protected function pregnant(): ?array
    {
        if (!$this->data->pregnant) {
            return null;
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

    /**
     * @return array<string, mixed>|null
     */
    protected function wheelchair(): ?array
    {
        if (!$this->data->wheelchair) {
            return null;
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

    /**
     * @return array<string, mixed>|null
     */
    protected function bmi(): ?array
    {
        if (!isset($this->summary['bmi'])) {
            return null;
        }
        $entry = $this->simpleMetric(
            'bmi',
            $this->summary['bmi'],
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

    /**
     * @return array<string, mixed>
     */
    protected function heartrate(int $replicate): array
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
            ],
            $this->getEffectiveDateTime('blood-pressure-source', $replicate)
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

        if (isset($this->data->{'heart-rate-position'})) {
            $entry['resource']['component'][] = $this->getPediatricComponent('heart-rate-position');
        }

        if (isset($this->data->{'heart-rate-method'})) {
            $entry['resource']['component'][] = $this->getPediatricComponent('heart-rate-method');
        }

        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function hipcircumference(int $replicate): array
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

    /**
     * @return array<string, mixed>
     */
    protected function waistcircumference(int $replicate): array
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

    /**
     * @return array<string, mixed>|null
     */
    protected function getBpBodySite(string $location): ?array
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

    /**
     * @return array<string, mixed>
     */
    protected function getWaistCircumferenceBodySite(): array
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

    // $replicate can be integer for replicate number OR 'mean'
    /**
     * @return array<string, mixed>|null
     */
    protected function getBpComponent(string $component, int|string $replicate): ?array
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

    /**
     * @return array<string, mixed>
     */
    protected function bloodpressure(int $replicate): array
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
        if (isset($this->data->{'blood-pressure-position'})) {
            $entry['resource']['component'][] = $this->getPediatricComponent('blood-pressure-position');
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

    /**
     * @return array<string, mixed>
     */
    protected function bloodpressureprotocolmodification(int $replicate): array
    {
        return $this->protocolModification('blood-pressure', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualbloodpressure(int $replicate): array
    {
        return $this->protocolModificationManual('blood-pressure', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function manualheartrate(int $replicate): array
    {
        return $this->protocolModificationManual('heart-rate', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function hipcircumferenceprotocolmodification(int $replicate): array
    {
        return $this->protocolModification('hip-circumference', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function waistcircumferenceprotocolmodification(int $replicate): array
    {
        return $this->protocolModification('waist-circumference', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function heightprotocolmodification(?int $replicate = null): array
    {
        return $this->protocolModification('height', $replicate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function weightprotocolmodification(?int $replicate = null): array
    {
        return $this->protocolModification('weight', $replicate);
    }

    /**
     * @param list<int> $replicates
     *
     * @return array<int, array<string, mixed>>
     */
    protected function meanProtocolModifications(array $replicates, string $modificationMetric, ?string $modificationMetricManual = null): array
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

    /**
     * @return array<string, mixed>
     */
    protected function bloodpressuremean(): array
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
        if ($related = $this->meanProtocolModifications([2, 3], 'blood-pressure-protocol-modification-', 'manual-blood-pressure-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function heartratemean(): array
    {
        $displayText = $this->isPediatricForm() ? 'Computed heart rate, mean of closest two measures' : 'Computed heart rate, mean of 2nd and 3rd measures';
        $entry = $this->simpleMetric(
            'heart-rate-mean',
            isset($this->summary['heartrate']) ? $this->summary['heartrate'] : null,
            $displayText,
            '/min',
            [
                [
                    'code' => '8867-4',
                    'display' => 'Heart rate',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'heart-rate-mean',
                    'display' => $displayText,
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
        if ($related = $this->meanProtocolModifications([2, 3], 'blood-pressure-protocol-modification-', 'manual-heart-rate-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function hipcircumferencemean(): array
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
        if ($related = $this->meanProtocolModifications([1, 2, 3], 'hip-circumference-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    protected function waistcircumferencemean(): array
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
        if ($related = $this->meanProtocolModifications([1, 2, 3], 'waist-circumference-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function notes(): ?array
    {
        if (!$this->data->notes) {
            return null;
        }
        return $this->stringMetric(
            'notes',
            $this->data->notes,
            'Additional notes',
            [[
                'code' => 'notes',
                'display' => 'Additional notes',
                'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
            ]]
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getEntry(string $metric): ?array
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
            }
            return $this->$method($replicate);
        }

        return null;
    }

    protected function getEffectiveDateTime(string $field, int $replicate = 1): string
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

    private function isPediatricForm(): bool
    {
        return str_contains($this->version, 'peds');
    }

    /**
     * @return array<string, mixed>
     */
    private function getPediatricComponent(string $propertyName): array
    {
        $propertyValue = $this->data->{$propertyName};

        $concept = [
            'coding' => [[
                'code' => $propertyValue,
                'display' => ucfirst($propertyValue),
                'system' => "http://terminology.pmi-ops.org/CodeSystem/{$propertyName}"
            ]],
            'text' => ucfirst($propertyValue)
        ];

        $component = [
            'code' => [
                'coding' => [[
                    'code' => $propertyName,
                    'display' => ucfirst($propertyName),
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]],
                'text' => ucfirst($propertyName)
            ],
            'valueCodeableConcept' => $concept
        ];

        return $component;
    }

    /**
     * @return array<string, mixed>
     */
    private function headcircumference(int $replicate): array
    {
        return $this->simpleMetric(
            'head-circumference-' . $replicate,
            $this->data->{'head-circumference'}[$replicate - 1],
            self::ordinalLabel('head circumference', $replicate),
            'cm',
            [
                [
                    'code' => '9843-4',
                    'display' => 'Head circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'head-circumference-' . $replicate,
                    'display' => self::ordinalLabel('head circumference', $replicate),
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('head-circumference-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforage(): array
    {
        return $this->getGrowthpercentileweightforage();
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforageMale(): array
    {
        return $this->getGrowthpercentileweightforage('male');
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforageFemale(): array
    {
        return $this->getGrowthpercentileweightforage('female');
    }

    /**
     * @return array<string, mixed>
     */
    private function getGrowthpercentileweightforage(?string $sex = null): array
    {
        $sexKey = $sex ? "-{$sex}" : '';
        $sexValue = $sex ?? '';
        return $this->simpleMetric(
            "growth-percentile-weight-for-age{$sexKey}",
            $this->summary["growth-percentile-weight-for-age{$sexKey}"] ?? null,
            "Computed growth percentile weight for age {$sexValue}",
            'percentile',
            [
                [
                    'code' => '22222-0',
                    'display' => "Growth percentile weight for age {$sexValue}",
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => "growth-percentile-weight-for-age{$sexKey}",
                    'display' => "Computed growth percentile weight for age {$sexValue}",
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('weight-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheightforage(): array
    {
        return $this->getGrowthpercentileheightforage();
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheightforageMale(): array
    {
        return $this->getGrowthpercentileheightforage('male');
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheightforageFemale(): array
    {
        return $this->getGrowthpercentileheightforage('female');
    }

    /**
     * @return array<string, mixed>
     */
    private function getGrowthpercentileheightforage(?string $sex = null): array
    {
        $sexKey = $sex ? "-{$sex}" : '';
        $sexValue = $sex ?? '';
        return $this->simpleMetric(
            "growth-percentile-height-for-age{$sexKey}",
            $this->summary["growth-percentile-height-for-age{$sexKey}"] ?? null,
            "Computed growth percentile height for age {$sexValue}",
            'percentile',
            [
                [
                    'code' => '33333-0',
                    'display' => "Growth percentile height for age {$sexValue}",
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => "growth-percentile-height-for-age{$sexKey}",
                    'display' => "Computed growth percentile weight for age {$sexValue}",
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('height-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforlength(): array
    {
        return $this->getGrowthpercentileweightforlength();
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforlengthMale(): array
    {
        return $this->getGrowthpercentileweightforlength('male');
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileweightforlengthFemale(): array
    {
        return $this->getGrowthpercentileweightforlength('female');
    }


    /**
     * @return array<string, mixed>
     */
    private function getGrowthpercentileweightforlength(?string $sex = null): array
    {
        $sexKey = $sex ? "-{$sex}" : '';
        $sexValue = $sex ?? '';
        return $this->simpleMetric(
            "growth-percentile-weight-for-length{$sexKey}",
            $this->summary["growth-percentile-weight-for-length{$sexKey}"] ?? null,
            "Computed growth percentile weight for length {$sexValue}",
            'percentile',
            [
                [
                    'code' => '44444-0',
                    'display' => "Growth percentile weight for length {$sexValue}",
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => "growth-percentile-weight-for-length{$sexValue}",
                    'display' => "Computed growth percentile weight for length {$sexValue}",
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('weight-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheadcircumferenceforage(): array
    {
        return $this->getGrowthpercentileheadcircumferenceforage();
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheadcircumferenceforageMale(): array
    {
        return $this->getGrowthpercentileheadcircumferenceforage('male');
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentileheadcircumferenceforageFemale(): array
    {
        return $this->getGrowthpercentileheadcircumferenceforage('female');
    }

    /**
     * @return array<string, mixed>
     */
    private function getGrowthpercentileheadcircumferenceforage(?string $sex = null): array
    {
        $sexKey = $sex ? "-{$sex}" : '';
        $sexValue = $sex ?? '';
        return $this->simpleMetric(
            "growth-percentile-head-circumference-for-age{$sexKey}",
            $this->summary["growth-percentile-head-circumference-for-age{$sexKey}"] ?? null,
            "Computed growth percentile head circumference for age {$sexValue}",
            'percentile',
            [
                [
                    'code' => '55555-0',
                    'display' => "Growth percentile head circumference for age {$sexValue}",
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => "growth-percentile-head-circumference-for-age{$sexKey}",
                    'display' => "Computed growth percentile head circumference for age {$sexValue}",
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('head-circumference-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentilebmiforage(): array
    {
        return $this->getGrowthpercentilebmiforage();
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentilebmiforageMale(): array
    {
        return $this->getGrowthpercentilebmiforage('male');
    }

    /**
     * @return array<string, mixed>
     */
    private function growthpercentilebmiforageFemale(): array
    {
        return $this->getGrowthpercentilebmiforage('female');
    }

    /**
     * @return array<string, mixed>
     */
    private function getGrowthpercentilebmiforage(?string $sex = null): array
    {
        $sexKey = $sex ? "-{$sex}" : '';
        $sexValue = $sex ?? '';
        return $this->simpleMetric(
            "growth-percentile-bmi-for-age{$sexKey}",
            $this->summary["growth-percentile-bmi-for-age{$sexKey}"] ?? null,
            "Computed growth percentile bmi for age {$sexValue}",
            'percentile',
            [
                [
                    'code' => '66666-0',
                    'display' => "Growth percentile bmi for age {$sexValue}",
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => "growth-percentile-bmi-for-age{$sexKey}",
                    'display' => "Computed growth percentile bmi for age {$sexValue}",
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ],
            $this->getEffectiveDateTime('weight-source')
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function headcircumferencemean(): array
    {
        $entry = $this->simpleMetric(
            'head-circumference-mean',
            !empty($this->summary['head']['cm']) ? $this->summary['head']['cm'] : null,
            'Computed head circumference, mean of closest two measures',
            'cm',
            [
                [
                    'code' => '11111-0',
                    'display' => 'Head circumference',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'head-circumference-mean',
                    'display' => 'Computed head circumference, mean of closest two measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if ($related = $this->meanProtocolModifications([1, 2, 3], 'head-circumference-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function heightmean(): array
    {
        $entry = $this->simpleMetric(
            'height-mean',
            !empty($this->summary['height']['cm']) ? $this->summary['height']['cm'] : null,
            'Computed height, mean of closest two measures',
            'cm',
            [
                [
                    'code' => '8302-2',
                    'display' => 'Body height',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'height-mean',
                    'display' => 'Computed height, mean of closest two measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if ($related = $this->meanProtocolModifications([1, 2, 3], 'height-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }

    /**
     * @return array<string, mixed>
     */
    private function weightmean(): array
    {
        $entry = $this->simpleMetric(
            'weight-mean',
            !empty($this->summary['weight']['kg']) ? $this->summary['weight']['kg'] : null,
            'Computed weight, mean of closest two measures',
            'kg',
            [
                [
                    'code' => '29463-7',
                    'display' => 'Body weight',
                    'system' => 'http://loinc.org'
                ],
                [
                    'code' => 'weight-mean',
                    'display' => 'Computed weight, mean of closest two measures',
                    'system' => 'http://terminology.pmi-ops.org/CodeSystem/physical-measurements'
                ]
            ]
        );
        if ($related = $this->meanProtocolModifications([1, 2, 3], 'weight-protocol-modification-')) {
            $entry['resource']['related'] = $related;
        }
        return $entry;
    }
}
