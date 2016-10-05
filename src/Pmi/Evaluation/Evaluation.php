<?php
namespace Pmi\Evaluation;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;
use Pmi\Util;

class Evaluation
{
    const CURRENT_VERSION = '0.1.1';
    protected $version;
    protected $data;
    protected $schema;
    protected $participant;
    protected $locked = false;

    public function __construct()
    {
        $this->version = self::CURRENT_VERSION;
        $this->data = new \StdClass();
        $this->loadSchema();
        $this->normalizeData();
    }

    public function loadFromArray($array)
    {
        if (array_key_exists('version', $array)) {
            $this->version = $array['version'];
        }
        if (array_key_exists('data', $array)) {
            if (is_object($array['data'])) {
                $this->data = $array['data'];
            } else {
                $this->data = json_decode($array['data']);
            }
        }
        if (!empty($array['finalized_ts'])) {
            $this->locked = true;
        }
        $this->participant = strtoupper(Util::shortenUuid($array['participant_id']));
        $this->loadSchema();
        $this->normalizeData();
    }

    public function toArray($serializeData = true)
    {
        return [
            'version' => $this->version,
            'data' => $serializeData ? json_encode($this->data) : $this->data
        ];
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getSchema()
    {
        return $this->schema;
    }

    public function getForm(FormFactory $formFactory)
    {
        $formBuilder = $formFactory->createBuilder(FormType::class, $this->data);
        foreach ($this->schema->fields as $field) {
            $constraints = [];
            $options = [
                'required' => false,
                'scale' => 0
            ];
            if ($this->locked) {
                $options['disabled'] = true;
            }
            if (isset($field->label)) {
                $options['label'] = $field->label;
            }
            if (isset($field->decimals)) {
                $options['scale'] = $field->decimals;
            }
            if (isset($field->max)) {
                $constraints[] = new Constraints\LessThanOrEqual($field->max);
            }
            if (isset($field->min)) {
                $constraints[] = new Constraints\GreaterThanOrEqual($field->min);
            } else {
                $constraints[] = new Constraints\GreaterThan(0);
            }
            $options['constraints'] = $constraints;

            if (isset($field->replicates)) {
                $formBuilder->add($field->name, CollectionType::class, [
                    'entry_type' => NumberType::class,
                    'entry_options' => $options,
                    'required' => false,
                    'label' => isset($options['label']) ? $options['label'] : null
                ]);
            } else {
                $formBuilder->add($field->name, NumberType::class, $options);
            }
        }
        return $formBuilder->getForm();
    }

    public function loadSchema()
    {
        $file = __DIR__ . "/versions/{$this->version}.json";
        if (!file_exists($file)) {
            throw new MissingSchemaException();
        }
        $this->schema = json_decode(file_get_contents($file));
        if (!is_object($this->schema) || !is_array($this->schema->fields)) {
            throw new InvalidSchemaException();
        }
        foreach ($this->schema->fields as $field) {
            if (!isset($this->data->{$field->name})) {
                $this->data->{$field->name} = null;
            }
        }
    }

    public function setData($data)
    {
        $this->data = $data;
        $this->normalizeData();
    }

    protected function normalizeData()
    {
        foreach ($this->data as $key => $value) {
            if (!is_array($value)) {
                $this->data->$key = floatval($value) ?: null;
            }
        }
        foreach ($this->schema->fields as $field) {
            if (isset($field->replicates)) {
                $key = $field->name;
                if (is_null($this->data->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $this->data->$key = $dataArray;
                }
                elseif (!is_null($this->data->$key) && !is_array($this->data->$key)) {
                    $dataArray = array_fill(0, $field->replicates, null);
                    $dataArray[0] = $this->data->$key;
                    $this->data->$key = $dataArray;
                }
            }
        }
    }

    public function getFhir()
    {
        $fhir = new \StdClass();
        $fhir->resourceType = 'Bundle';
        $fhir->type = 'document';
        $date = (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z');
        $entries = [];
        $compositionEntry = [
            'fullUrl' => 'urn:example:report',
            'resource' => [
                'author' => [['display' => 'N/A']],
                'date' => $date,
                'resourceType' => 'Composition',
                'section' => [
                    'entry' => [
                        ['reference' => 'urn:example:height']
                    ]
                ],
                'status' => 'final',
                'subject' => "Patient/{$this->participant}",
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
        $entries[] = $compositionEntry;
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
                'effectiveDateTime' => $date,
                'resourceType' => 'Observation',
                'status' => 'final',
                'subject' => [
                    'reference' => "Patient/{$this->participant}"
                ],
                'valueQuantity' => [
                    'code' => 'cm',
                    'system' => 'http://unitsofmeasure.org',
                    'unit' => 'cm',
                    'value' => $this->data->height
                ]
            ]
        ];
        $entries[] = $heightEntry;
        $fhir->entry = $entries;
        return $fhir;
    }
}
