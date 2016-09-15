<?php
namespace Pmi\Evaluation;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Validator\Constraints;

class Evaluation
{
    const CURRENT_VERSION = '0.1';
    protected $version;
    protected $data;
    protected $schema;

    public function __construct()
    {
        $this->version = self::CURRENT_VERSION;
        $this->data = new \StdClass();
        $this->loadSchema();
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
            $this->normalizeData();
        }
        $this->loadSchema();
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
            $formBuilder->add($field->name, NumberType::class, $options);
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
    }

    protected function normalizeData()
    {
        foreach ($this->data as $key => $value) {
            $this->data->$key = floatval($value) ?: null;
        }
    }
}
