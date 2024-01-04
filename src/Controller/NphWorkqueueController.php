<?php

namespace App\Controller;

use App\Service\WorkqueueGeneralizedService;
use App\WorkQueue\DataSources\NphDataSource;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NphWorkqueueController extends AbstractController
{
    #[Route('/nph/workqueue', name: 'nph_workqueue')]
    public function index(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource): Response
    {
        $workqueueService->setDataSource($dataSource);
        $workqueueService->loadWorkqueueColumns('NPH');
        $columnHeaders = $workqueueService->getWorkqueueColumnHeaders();
        $columns = $workqueueService->getColumnCollection();
        $sortableColumns = $workqueueService->getSortableColumns();
        return $this->render('nph_workqueue/index.html.twig', [
            'controller_name' => 'NphWorkqueueController',
            'columnHeaders' => $columnHeaders,
            'columns' => $columns,
            'columnGroups' => $workqueueService->getColumnGroups(),
            'sortableColumns' => $sortableColumns
        ]);
    }

    #[Route('/nph/workqueue/data', name: 'app_nph_workqueue_data')]
    public function data(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource, Request $request): Response
    {
        $workqueueService->loadWorkqueueColumns('NPH');
        $workqueueService->setDataSource($dataSource);
        $data = $workqueueService->getWorkqueueData($request->get('start'), $request->get('length'));
        return $this->json($data);
    }
}
