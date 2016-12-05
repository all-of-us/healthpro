<?php
namespace Tests\Pmi\Evaluation;

use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Pmi\Evaluation\Evaluation;

class EvaluationFormTest extends TypeTestCase
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

        $evaluationService = new Evaluation();
        $form = $evaluationService->getForm($this->factory);
        $form->submit($formData);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($form->isSubmitted());
        $this->assertTrue($form->isValid());
        $formData = $form->getData();
        $view = $form->createView();

        $fields = array_keys($evaluationService->getAssociativeSchema()->fields);
        $this->assertSame($fields, array_keys($view->children));
        $this->assertSame($fields, array_keys((array)$formData));
    }
}
