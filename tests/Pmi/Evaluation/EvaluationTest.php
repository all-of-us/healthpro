<?php
use Pmi\Evaluation\Evaluation;
use Pmi\Evaluation\MissingSchemaException;
use Symfony\Component\Form\Form;
use Tests\Pmi\AbstractWebTestCase;

class EvaluationTest extends AbstractWebTestCase
{
    public function testSchema()
    {
        $evaluation = new Evaluation();
        $schema = $evaluation->getSchema();
        $this->assertEquals(Evaluation::CURRENT_VERSION, $schema->version);
        $this->assertTrue(is_array($schema->fields));
    }

    public function testMissingSchema()
    {
        $this->setExpectedException(MissingSchemaException::class);
        $evaluation = new Evaluation();
        $evaluation->loadFromArray(['version' => '0.0a', 'participant_id' => 'test']);
        $schema = $evaluation->getSchema();
    }

    public function testForm()
    {
        $evaluation = new Evaluation();
        $form = $evaluation->getForm($this->app['form.factory']);
        $this->assertInstanceOf(Form::class, $form);
    }
}
