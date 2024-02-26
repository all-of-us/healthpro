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
        $workqueueService->setSort($request->query);
        $data = $workqueueService->getWorkqueueData($request->get('start'), $request->get('length'));
        return $this->json($data);
    }

    #[Route('/nph/workqueue/export', name: 'nph_workqueue_export')]
    public function export(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource, Request $request): Response
    {
        $workqueueService->loadWorkqueueColumns('NPH');
        $workqueueService->setDataSource($dataSource);
        $workqueueService->setSearch($request->get('search'));
        $workqueueService->hasMoreResults();
        $data = $workqueueService->getWorkqueueData(0, 100000);
        $csv = $workqueueService->exportToCsv($data['data']);
        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="workqueue.csv"');
        return $response;
    }

    //Todo: Remove before production merge
    #[Route('/nph/workqueue/data/rawquery', name: 'app_nph_workqueue_rawquery')]
    public function rawQuery(WorkqueueGeneralizedService $workqueueService, NphDataSource $dataSource, Request $request): Response
    {
        $workqueueService->setDataSource($dataSource);
        $test = $workqueueService->rawQuery(json_decode($request->getContent())->query);
        return $this->json(['data' => $test]);
    }
}
