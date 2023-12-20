<?php

namespace App\Controller;

use App\WorkQueue\DataSources\NphDataSource;
use App\WorkQueue\WorkqueueService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NphWorkqueueController extends AbstractController
{
    #[Route('/nph/workqueue', name: 'app_nph_workqueue')]
    public function index(WorkqueueService $workqueueService, NphDataSource $dataSource): Response
    {
        $workqueueService->setDataSource($dataSource);
        $workqueueService->loadWorkqueueColumns('NPH');
        $columnHeaders = $workqueueService->getWorkqueueColumnHeaders();
        $columns = $workqueueService->getColumnCollection();
        return $this->render('nph_workqueue/index.html.twig', [
            'controller_name' => 'NphWorkqueueController',
            'columnHeaders' => $columnHeaders,
            'columns' => $columns,
            'columnGroups' => $workqueueService->getColumnGroups()
        ]);
    }

    #[Route('/nph/workqueue/data', name: 'app_nph_workqueue_data')]
    public function data(WorkqueueService $workqueueService, NphDataSource $dataSource): Response
    {
        $workqueueService->loadWorkqueueColumns('NPH');
        $dataSource->setColumnCollection($workqueueService->getColumnCollection());
        $workqueueService->setDataSource($dataSource);
        $data = $workqueueService->getWorkqueueData(0, 10);
        return $this->json([
            'data' => $data
        ]);
    }
}
