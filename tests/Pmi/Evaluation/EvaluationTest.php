<?php
use Pmi\Application\HpoApplication;
use Pmi\Evaluation\Evaluation;
use Pmi\Evaluation\MissingSchemaException;
use Symfony\Component\Form\Form;

class EvaluationTest extends \PHPUnit_Framework_TestCase
{
    protected function getApplication()
    {
        putenv('PMI_ENV=' . HpoApplication::ENV_DEV);
        $app = new HpoApplication([
            'templatesDirectory' => __DIR__ . '/../../../views',
            'errorTemplate' => 'error.html.twig',
            'isUnitTest' => true
        ]);
        $app->setup();
        $app->register(new \Silex\Provider\SessionServiceProvider(), [
            'session.test' => true
        ]);
        return $app;
    }

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
        $evaluation->loadFromArray(['version' => '0.0a']);
        $schema = $evaluation->getSchema();
    }

    public function testForm()
    {
        $app = $this->getApplication();
        $evaluation = new Evaluation();
        $form = $evaluation->getForm($app['form.factory']);
        $this->assertInstanceOf(Form::class, $form);
    }
}
