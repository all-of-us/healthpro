<?php

namespace App\Tests\Form;

use App\Entity\Measurement;
use App\Form\MeasurementType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

class MeasurementFormTest extends KernelTestCase
{
    public function testSubmitValidData()
    {
        self::bootKernel();
        /** @var FormFactoryInterface $formFactory */
        $formFactory = static::getContainer()->get('form.factory');

        $formData = [
            'height' => '180',
            'weight' => '70'
        ];

        $measurement = new Measurement();
        $measurement->loadFromAObject();
        $form = $formFactory->create(MeasurementType::class, $measurement->getFieldData(), [
            'schema' => $measurement->getSchema(),
            'locked' => $measurement->getFinalizedTs() ? true : false,
            'csrf_protection' => false,
        ]);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $formData = $form->getData();
        $view = $form->createView();

        $fields = array_keys($measurement->getAssociativeSchema()->fields);
        $this->assertSame($fields, array_keys($view->children));
        $this->assertSame($fields, array_keys((array) $formData));
    }
}
