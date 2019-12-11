<?php
use Tests\Pmi\AbstractWebTestCase;

class AwardeesTest extends AbstractWebTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->removeTestAwardees();
    }

    protected function tearDown()
    {
        $this->removeTestAwardees();
    }

    protected function removeTestAwardees()
    {
        $awardeesRepository = $this->app['em']->getRepository('awardees');
        $awardeesRepository->delete('UNITTEST');
        $awardeesRepository->delete('UNITTEST ');
    }

    public function testTrailingSpaceNoCollision()
    {
        $awardeesRepository = $this->app['em']->getRepository('awardees');
        $result = $awardeesRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
        $result = $awardeesRepository->insert([
            'id' => 'UNITTEST ',
            'name' => 'Unit test'
        ]);

        $awardee = $awardeesRepository->fetchOneBy(['id' => 'UNITTEST ']);
        $this->assertNotNull($awardee);
        $this->assertSame('UNITTEST ', $awardee['id']);
    }

    /**
     * @expectedException Doctrine\DBAL\Exception\UniqueConstraintViolationException
     */
    public function testDuplicateIdCollision()
    {
        $awardeesRepository = $this->app['em']->getRepository('awardees');
        $result = $awardeesRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
        $result = $awardeesRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
    }
}
