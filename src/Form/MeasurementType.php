<?php

namespace App\Form;

use App\Entity\Measurement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints;

class MeasurementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        foreach ($options['schema']->fields as $field) {
            if (isset($field->formField) && !$field->formField) {
                continue;
            }
            if (isset($field->type)) {
                $type = $field->type;
            } else {
                $type = null;
            }
            $constraints = [];
            $attributes = [];
            $fieldOptions = [
                'required' => false,
                'scale' => 0
            ];
            if ($options['locked']) {
                $fieldOptions['disabled'] = true;
            }
            if (isset($field->label)) {
                $fieldOptions['label'] = $field->label;
            }
            if (isset($field->decimals)) {
                $fieldOptions['scale'] = $field->decimals;
            }
            if (isset($field->max)) {
                $constraints[] = new Constraints\LessThan($field->max);
                $attributes['data-parsley-lt'] = $field->max;
            }
            if (isset($field->min)) {
                $constraints[] = new Constraints\GreaterThanOrEqual($field->min);
                $attributes['data-parsley-gt'] = $field->min;
            } elseif (!isset($field->options) && !in_array($type, ['checkbox', 'text', 'textarea', 'date'])) {
                $constraints[] = new Constraints\GreaterThan(0);
                $attributes['data-parsley-gt'] = 0;
            }
            $form = $builder->getForm();
            $bmiConstraint = function ($value, $context) use ($form) {
                $bmi = round(self::calculateBmi($form->getData()->height, $form->getData()->weight), 1);
                if ($bmi != false && ($bmi < 5 || $bmi > 125)) {
                    $context->buildViolation('This height/weight combination has yielded an invalid BMI')->addViolation();
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

            if (isset($field->alternateunitfield, $field->alternatefor) && $field->alternateunitfield && $field->alternatefor) {
                $attributes['id'] = 'alt-units-' . $field->alternatefor;
                $attributes['class'] = "form-control alt-units-$field->alternatefor";
            }

            if (isset($field->options)) {
                $class = Type\ChoiceType::class;
                unset($fieldOptions['scale']);
                if (is_array($field->options)) {
                    $fieldOptions['choices'] = array_combine($field->options, $field->options);
                } else {
                    $fieldOptions['choices'] = (array) $field->options;
                }
                $fieldOptions['placeholder'] = false;
            } elseif ($type === 'checkbox') {
                unset($fieldOptions['scale']);
                $class = Type\CheckboxType::class;
            } elseif ($type === 'textarea') {
                unset($fieldOptions['scale']);
                $class = Type\TextareaType::class;
                $attributes['rows'] = 4;
                $attributes['data-parsley-maxlength'] = Measurement::LIMIT_TEXT_LONG;
                $constraints[] = new Constraints\Length(['max' => Measurement::LIMIT_TEXT_LONG]);
                $constraints[] = new Constraints\Type('string');
            } elseif ($type === 'text') {
                unset($fieldOptions['scale']);
                $class = Type\TextType::class;
                $attributes['data-parsley-maxlength'] = Measurement::LIMIT_TEXT_SHORT;
                $constraints[] = new Constraints\Length(['max' => Measurement::LIMIT_TEXT_SHORT]);
                $constraints[] = new Constraints\Type('string');
            } elseif ($type === 'date') {
                unset($fieldOptions['scale']);
                $class = Type\DateType::class;
                $minDate = new \DateTime('-6 months');
                $minDate->modify('-1 day')->setTime(0, 0, 0);
                $constraints[] = new Constraints\Range([
                    'min' => $minDate,
                    'max' => new \DateTime('today'),
                    'minMessage' => 'Date cannot be greater than six months in the past',
                    'maxMessage' => 'Date cannot be in the future',
                    'notInRangeMessage' => 'Date cannot be greater than six months in the past and cannot be in the future'
                ]);
                $dateOptions = [
                    'widget' => 'single_text',
                    'format' => 'MM/dd/yyyy',
                    'html5' => false,
                    'required' => false
                ];
                $fieldOptions = array_merge($fieldOptions, $dateOptions);
                $attributes['class'] = 'ehr-date';
                $attributes['autocomplete'] = 'off';
            } else {
                $class = Type\NumberType::class;
                $constraints[] = new Constraints\Type('numeric');
            }

            $fieldOptions['constraints'] = $constraints;
            $fieldOptions['attr'] = $attributes;

            if ($type === 'radio') {
                $fieldOptions['expanded'] = true;
            }

            if (isset($field->replicates)) {
                $collectionOptions = [
                    'entry_type' => $class,
                    'entry_options' => $fieldOptions,
                    'required' => false,
                    'label' => isset($fieldOptions['label']) ? $fieldOptions['label'] : null
                ];
                if (isset($field->compare)) {
                    $collectionOptions['constraints'] = $this->addDiastolicBloodPressureConstraint($form, $field);
                }
                $builder->add($field->name, CollectionType::class, $collectionOptions);
            } else {
                $builder->add($field->name, $class, $fieldOptions);
            }
        }
        return $builder->getForm();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'schema' => null,
            'locked' => null
        ]);
    }

    protected static function calculateBmi($height, $weight)
    {
        if ($height && $weight) {
            return $weight / (($height / 100) * ($height / 100));
        }
        return false;
    }

    private function addDiastolicBloodPressureConstraint($form, $field)
    {
        $compareType = $field->compare->type;
        $compareField = $field->compare->field;
        $compareMessage = $field->compare->message;
        $callback = function ($value, $context, $replicate) use ($form, $compareField, $compareType, $compareMessage) {
            $compareTo = $form->getData()->$compareField;
            if (!isset($compareTo[$replicate])) {
                return;
            }
            if ($compareType === 'greater-than' && $value <= $compareTo[$replicate]) {
                $context->buildViolation($compareMessage)->addViolation();
            } elseif ($compareType === 'less-than' && $value >= $compareTo[$replicate]) {
                $context->buildViolation($compareMessage)->addViolation();
            }
        };
        $collectionConstraintFields = [];
        for ($i = 0; $i < $field->replicates; $i++) {
            $collectionConstraintFields[] = new Constraints\Callback(['callback' => $callback, 'payload' => $i]);
        }
        $compareConstraint = new Constraints\Collection($collectionConstraintFields);
        return [$compareConstraint];
    }
}
