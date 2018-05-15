<?php
namespace Pmi\Evaluation;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;
use Pmi\Util;

class Evaluation
{
    const CURRENT_VERSION = '0.3.3';
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

    public function loadFromArray($array, $app = null)
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
        $this->participant = $array['participant_id'];
        if ($app) {
            $createdUser = $app['em']->getRepository('users')->fetchOneBy([
                'id' => $array['user_id']
            ]);
            if (!$array['finalized_user_id']) {
                $finalizedUserId = $array['finalized_ts'] ? $array['user_id'] : $app->getUser()->getId();
                $finalizedSite = $array['finalized_ts'] ? $array['site'] : $app->getSiteId();
            } else {
                $finalizedUserId = $array['finalized_user_id'];
                $finalizedSite = $array['finalized_site'];          
            }
            $finalizedUser = $app['em']->getRepository('users')->fetchOneBy([
                'id' => $finalizedUserId
            ]);
            $this->createdUser = $createdUser['email'];
            $this->createdSite = $array['site'];
            $this->finalizedUser = $finalizedUser['email'];
            $this->finalizedSite = $finalizedSite;
        }
        else {
            $this->createdUser = array_key_exists('created_user', $array) ? $array['created_user'] : null;
            $this->createdSite = array_key_exists('created_site', $array) ? $array['created_site'] : null;
            $this->finalizedUser = array_key_exists('finalized_user', $array) ? $array['finalized_user'] : null;
            $this->finalizedSite = array_key_exists('finalized_site', $array) ? $array['finalized_site'] : null;
        }
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

    public function getAssociativeSchema()
    {
        $schema = clone $this->schema;
        $associativeFields = [];
        foreach ($schema->fields as $field) {
            $associativeFields[$field->name] = $field;
        }
        $schema->fields = $associativeFields;
        return $schema;
    }

    public function getWarnings()
    {
        $warnings = [];
        foreach ($this->schema->fields as $metric) {
            if (!empty($metric->warnings) && is_array($metric->warnings)) {
                $warnings[$metric->name] = $metric->warnings;
            }
        }
        return $warnings;
    }

    public function getConversions()
    {
        $conversions = [];
        foreach ($this->schema->fields as $metric) {
            if (!empty($metric->convert)) {
                $conversions[$metric->name] = $metric->convert;
            }
        }
        return $conversions;
    }

    public function getForm(FormFactory $formFactory)
    {
        $formBuilder = $formFactory->createBuilder(FormType::class, $this->data);
        foreach ($this->schema->fields as $field) {
            if (isset($field->type)) {
                $type = $field->type;
            } else {
                $type = null;
            }
            $constraints = [];
            $attributes = [];
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
                $constraints[] = new Constraints\LessThan($field->max);
                $attributes['data-parsley-lt'] = $field->max;
            }
            if (isset($field->min)) {
                $constraints[] = new Constraints\GreaterThanEqual($field->min);
                $attributes['data-parsley-gt'] = $field->min;
            } elseif (!isset($field->options) && !in_array($type, ['checkbox', 'text', 'textarea'])) {
                $constraints[] = new Constraints\GreaterThan(0);
                $attributes['data-parsley-gt'] = 0;
            }
            $form = $formBuilder->getForm();
            $bmiConstraint = function($value, $context) use ($form) {
                $bmi = round(self::calculateBmi($form->getData()->height, $form->getData()->weight), 1);
                if ($bmi !== false && ($bmi < 5 || $bmi > 250)) {
                    $context->buildViolation('Invalid height/weight combination')->addViolation();
                }
            };

            if ($field->name === 'height') {
                $attributes['data-parsley-bmi-height'] = '#form_weight';
                $constraints[] = new Constraints\Callback($bmiConstraint);
            }
            if ($field->name === 'weight') {
                $attributes['data-parsley-bmi-weight'] = '#form_height';
                $constraints[] = new Constraints\Callback($bmiConstraint);
            }

            if (isset($field->options)) {
                $class = ChoiceType::class;
                unset($options['scale']);
                if (is_array($field->options)) {
                    $options['choices'] = array_combine($field->options, $field->options);
                } else {
                    $options['choices'] = (array)$field->options;
                }
                $options['placeholder'] = false;
            } elseif ($type == 'checkbox') {
                unset($options['scale']);
                $class = CheckboxType::class;
            } elseif ($type == 'textarea') {
                unset($options['scale']);
                $class = TextareaType::class;
                $attributes['rows'] = 4;
                $constraints[] = new Constraints\Type('string');
            } elseif ($type == 'text') {
                unset($options['scale']);
                $class = TextType::class;
                $constraints[] = new Constraints\Type('string');
            } else {
                $class = NumberType::class;
                $constraints[] = new Constraints\Type('numeric');
            }

            $options['constraints'] = $constraints;
            $options['attr'] = $attributes;

            if (isset($field->replicates)) {
                $collectionOptions = [
                    'entry_type' => $class,
                    'entry_options' => $options,
                    'required' => false,
                    'label' => isset($options['label']) ? $options['label'] : null
                ];
                if (isset($field->compare)) {
                    $compareType = $field->compare->type;
                    $compareField = $field->compare->field;
                    $compareMessage = $field->compare->message;
                    $callback = function($value, $context, $replicate) use ($form, $compareField, $compareType, $compareMessage) {
                        $compareTo = $form->getData()->$compareField;
                        if (!isset($compareTo[$replicate])) {
                            return;
                        }
                        if ($compareType == 'greater-than' && $value <= $compareTo[$replicate]) {
                            $context->buildViolation($compareMessage)->addViolation();
                        } elseif ($compareType == 'less-than' && $value >= $compareTo[$replicate]) {
                            $context->buildViolation($compareMessage)->addViolation();
                        }
                    };
                    $collectionConstraintFields = [];
                    for ($i = 0; $i < $field->replicates; $i++) {
                        $collectionConstraintFields[] = new Constraints\Callback(['callback' => $callback, 'payload' => $i]);
                    }
                    $compareConstraint = new Constraints\Collection($collectionConstraintFields);
                    $collectionOptions['constraints'] = [$compareConstraint];
                }
                $formBuilder->add($field->name, CollectionType::class, $collectionOptions);
            } else {
                $formBuilder->add($field->name, $class, $options);
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
            if ($value === 0) {
                $this->data->$key = null;
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

    public function getFhir($datetime, $parentRdr = null)
    {
        $fhir = new Fhir([
            'data' => $this->data,
            'schema' => $this->getAssociativeSchema(),
            'patient' => $this->participant,
            'version' => $this->version,
            'datetime' => $datetime,
            'parent_rdr' => $parentRdr,
            'created_user' => $this->createdUser,
            'created_site' => $this->createdSite,
            'finalized_user' => $this->finalizedUser,
            'finalized_site' => $this->finalizedSite,
            'summary' => $this->getSummary()
        ]);
        return $fhir->toObject();
    }

    protected function isMinVersion($minVersion)
    {
        return Util::versionIsAtLeast($this->version, $minVersion);
    }

    public function getFinalizeErrors()
    {
        $errors = [];

        if (!$this->isMinVersion('0.3.0')) {
            // prior to version 0.3.0, any state is valid
            return $errors;
        }

        foreach (['blood-pressure-systolic', 'blood-pressure-diastolic', 'heart-rate'] as $field) {
            foreach ($this->data->$field as $k => $value) {
                if (!$this->data->{'blood-pressure-protocol-modification'}[$k] && !$value) {
                    $errors[] = [$field, $k];
                }
            }
        }
        foreach (['height', 'weight'] as $field) {
            if (!$this->data->{$field . '-protocol-modification'} && !$this->data->$field) {
                $errors[] = $field;
            }
        }
        if (!$this->data->pregnant && !$this->data->wheelchair) {
            foreach (['hip-circumference', 'waist-circumference'] as $field) {
                foreach ($this->data->$field as $k => $value) {
                    if ($k == 2) {
                        // not an error on the third measurement if first two aren't completed
                        // or first two measurements are within 1 cm
                        if (!$this->data->{$field}[0] || !$this->data->{$field}[1]) {
                            break;
                        }
                        if (abs($this->data->{$field}[0] - $this->data->{$field}[1]) <= 1) {
                            break;
                        }
                    }
                    if (!$this->data->{$field . '-protocol-modification'}[$k] && !$value) {
                        $errors[] = [$field, $k];
                    }
                }
            }
        }

        return $errors;
    }

    protected static function cmToFtIn($cm)
    {
        $inches = self::cmToIn($cm);
        $feet = floor($inches / 12);
        $inches = round(fmod($inches, 12));
        return "$feet ft $inches in";
    }

    protected static function cmToIn($cm)
    {
        return round($cm * 0.3937, 1);
    }

    protected static function kgToLb($kg)
    {
        return round($kg * 2.2046, 1);
    }

    protected function calculateMean($field)
    {
        $secondThirdFields = [
            'blood-pressure-systolic',
            'blood-pressure-diastolic',
            'heart-rate'
        ];
        $twoClosestFields = [
            'hip-circumference',
            'waist-circumference'
        ];
        if (in_array($field, $secondThirdFields)) {
            $values = [$this->data->{$field}[1], $this->data->{$field}[2]];
        } else {
            $values = $this->data->{$field};
        }
        $values = array_filter($values);
        if (count($values) > 0) {
            if (count($values) === 3 && in_array($field, $twoClosestFields)) {
                sort($values);
                if ($values[1] - $values[0] < $values[2] - $values[1]) {
                    array_pop($values);
                } elseif ($values[2] - $values[1] < $values[1] - $values[0]) {
                    array_shift($values);
                }
            }
            return array_sum($values) / count($values);
        } else {
            return null;
        }
    }

    protected static function calculateBmi($height, $weight)
    {
        if ($height && $weight) {
            return $weight / (($height / 100) * ($height / 100));
        }
        return false;
    }

    public function getSummary()
    {
        $summary = [];
        if ($this->data->height) {
            $summary['height'] = [
                'cm' => $this->data->height,
                'ftin' => self::cmToFtIn($this->data->height)
            ];
        }
        if ($this->data->weight) {
            $summary['weight'] = [
                'kg' => $this->data->weight,
                'lb' => self::kgToLb($this->data->weight)
            ];
        }
        if ($this->data->weight && $this->data->height) {
            $summary['bmi'] = self::calculateBmi($this->data->height, $this->data->weight);
        }
        if ($hip = $this->calculateMean('hip-circumference')) {
            $summary['hip'] = [
                'cm' => $hip,
                'in' => self::cmToIn($hip)
            ];
        }
        if ($waist = $this->calculateMean('waist-circumference')) {
            $summary['waist'] = [
                'cm' => $waist,
                'in' => self::cmToIn($waist)
            ];
        }
        $systolic = $this->calculateMean('blood-pressure-systolic');
        $diastolic = $this->calculateMean('blood-pressure-diastolic');
        if ($systolic && $diastolic) {
            $summary['bloodpressure'] = [
                'systolic' => $systolic,
                'diastolic' => $diastolic
            ];
        }
        if ($heartrate = $this->calculateMean('heart-rate')) {
            $summary['heartrate'] = $heartrate;
        }
        return $summary;
    }
}
