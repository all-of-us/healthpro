<?php
use Tests\Pmi\AbstractWebTestCase;

class OrganizationsTest extends AbstractWebTestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->removeTestOrganizations();
    }

    protected function tearDown()
    {
        $this->removeTestOrganizations();
    }

    protected function removeTestOrganizations()
    {
        $organizationsRepository = $this->app['em']->getRepository('organizations');
        $organizationsRepository->delete('UNITTEST');
        $organizationsRepository->delete('UNITTEST ');
    }

    public function testTrailingSpaceNoCollision()
    {
        $organizationsRepository = $this->app['em']->getRepository('organizations');
        $result = $organizationsRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
        $result = $organizationsRepository->insert([
            'id' => 'UNITTEST ',
            'name' => 'Unit test'
        ]);

        $organization = $organizationsRepository->fetchOneBy(['id' => 'UNITTEST ']);
        $this->assertNotNull($organization);
        $this->assertSame('UNITTEST ', $organization['id']);
    }

    /**
     * @expectedException Doctrine\DBAL\Exception\UniqueConstraintViolationException
     */
    public function testDuplicateIdCollision()
    {
        $organizationsRepository = $this->app['em']->getRepository('organizations');
        $result = $organizationsRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
        $result = $organizationsRepository->insert([
            'id' => 'UNITTEST',
            'name' => 'Unit test'
        ]);
    }
}
