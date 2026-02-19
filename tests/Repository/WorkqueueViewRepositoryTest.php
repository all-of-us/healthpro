<?php

namespace App\Tests\Repository;

use App\Entity\Incentive;
use App\Entity\User;
use App\Entity\WorkqueueView;
use App\Repository\IncentiveRepository;
use App\Repository\WorkqueueViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkqueueViewRepositoryTest extends KernelTestCase
{
    private $em;
    private $repo;
    private $user;

    public function setup(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repo = static::getContainer()->get(WorkqueueViewRepository::class);
        $this->user = $this->getUser();

    }

    public function testUpdateDefaultView(): void
    {
        $this->createViews();
        $workqueueView = $this->repo->findOneBy([
            'name' => 'Test View 1',
            'user' => $this->user
        ]);
        $id = $workqueueView->getId();
        $this->repo->updateDefaultView($id, $this->user);
        $workqueueView = $this->repo->findOneBy([
            'name' => 'Test View 2',
            'user' => $this->user
        ]);
        $this->em->refresh($workqueueView);
        $this->assertEquals(false, $workqueueView->getDefaultView());
    }

    /**
     * @dataProvider duplicateViewDataProvider
     */
    public function testCheckDuplicateName($checkId, $name, $duplicateCount): void
    {
        $this->createViews();
        $id = null;
        if ($checkId) {
            $workqueueView = $this->repo->findOneBy([
                'name' => $name,
                'user' => $this->user
            ]);
            $id = $workqueueView->getId();
        }
        $workqueueViewCount = $this->repo->checkDuplicateName($id, $name, $this->user);
        $this->assertEquals($duplicateCount, $workqueueViewCount);
    }

    public function duplicateViewDataProvider(): array
    {
        return [
            [false, 'Test View 1', 1],
            [false, 'Test View 0', 0],
            [true, 'Test View 1', 0],
        ];
    }

    private function createViews(): void
    {
        foreach ($this->getViewsData() as $viewData) {
            $workqueueView = new WorkqueueView();
            $workqueueView->setUser($this->user)
                ->setName($viewData['name'])
                ->setDefaultView($viewData['defaultView'])
                ->setCreatedTs(new \DateTime($viewData['createdTs']))
                ->setFilters($viewData['filters'])
                ->setColumns($viewData['columns']);
            $this->em->persist($workqueueView);
        }
        $this->em->flush();
    }

    private function getUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setGoogleId('12345');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function getViewsData(): array
    {
        return [
            [
                'site' => 'PS_SITE_TEST',
                'name' => 'Test View 1',
                'createdTs' => '2022-01-15',
                'defaultView' => 1,
                'filters' => null,
                'columns' => null
            ],
            [
                'site' => 'PS_SITE_TEST',
                'name' => 'Test View 2',
                'createdTs' => '2022-02-15',
                'defaultView' => 1,
                'filters' => null,
                'columns' => null
            ],
            [
                'site' => 'PS_SITE_TEST',
                'name' => 'Test View 3',
                'createdTs' => '2022-03-15',
                'defaultView' => 1,
                'filters' => null,
                'columns' => null
            ],
            [
                'site' => 'PS_SITE_TEST',
                'name' => 'Test View 4',
                'createdTs' => '2022-04-15',
                'defaultView' => 0,
                'filters' => null,
                'columns' => null
            ],
            [
                'site' => 'PS_SITE_TEST',
                'name' => 'Test View 5',
                'createdTs' => '2022-05-15',
                'defaultView' => 0,
                'filters' => null,
                'columns' => null
            ],
        ];
    }
}
