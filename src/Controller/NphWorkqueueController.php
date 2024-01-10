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
        $fieldNames = $workqueueService->getWorkqueueFieldNames();
        $columns = $workqueueService->getColumnCollection();
        $sortableColumns = $workqueueService->getSortableColumns();
        return $this->render('workqueue/generalized/index.html.twig', [
            'columnHeaders' => $columnHeaders,
            'fieldNames' => $fieldNames,
            'columns' => $columns,
            'columnGroups' => $workqueueService->getColumnGroups(),
            'filteredColumnGroups' => $workqueueService->getFilteredColumnGroups(),
            'sortableColumns' => $sortableColumns
        ]);
    }

    #[Route('/nph/workqueue/data', name: 'app_nph_workqueue_data')]
    public function data(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource, Request $request): Response
    {
        $workqueueService->loadWorkqueueColumns('NPH');
        $workqueueService->setDataSource($dataSource);
        $workqueueService->setSearch($request->get('search'));
        $data = $workqueueService->getWorkqueueData($request->get('start'), $request->get('length'));
        return $this->json($data);
    }

    #[Route('/nph/workqueue/data/rawquery', name: 'app_nph_workqueue_rawquery')]
    public function rawQuery(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource, Request $request): Response
    {
        $workqueueService->setDataSource($dataSource);
        $test = $workqueueService->rawQuery(json_decode($request->getContent())->query);
        return $this->json(["data" => $test]);
    }
}
