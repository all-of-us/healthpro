<?php

namespace App\Test\Form;

use App\Entity\Measurement;
use App\Form\MeasurementType;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class MeasurementFormTest extends TypeTestCase
{
    protected function getExtensions()
    {
        $validator = $this
            ->getMockBuilder('Symfony\Component\Validator\Validator\ValidatorInterface')
            ->getMock();
        $validator
            ->method('validate')
            ->will($this->returnValue(new ConstraintViolationList()));
        $validator
            ->method('getMetadataFor')
            ->will($this->returnValue(new ClassMetadata('Symfony\Component\Form\Form')));
        return [
            new ValidatorExtension($validator),
        ];
    }

    public function testSubmitValidData()
    {
        $formData = [
            'height' => '180',
            'weight' => '70'
        ];

        $measurement = new Measurement;
        $measurement->loadFromAObject();
        $form = $this->factory->create(MeasurementType::class, $measurement->getFieldData(), [
            'schema' => $measurement->getSchema(),
            'locked' => $measurement->getFinalizedTs() ? true : false
        ]);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $formData = $form->getData();
        $view = $form->createView();

        $fields = array_keys($measurement->getAssociativeSchema()->fields);
        $this->assertSame($fields, array_keys($view->children));
        $this->assertSame($fields, array_keys((array)$formData));
    }
}
