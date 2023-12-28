<?php

namespace App\Service;

use App\WorkQueue\ColumnCollection;
use App\WorkQueue\ColumnDefs\defaultColumn;
use App\WorkQueue\DataSources\WorkqueueDatasource;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkqueueGeneralizedService
{
    private WorkqueueDatasource $datasource;
    private ColumnCollection $columnCollection;
    private array $columnGroups = [];
    private UrlGeneratorInterface $route;
    private SiteService $siteService;
    public function __construct(UrlGeneratorInterface $route, SiteService $siteService)
    {
        $this->route = $route;
        $this->siteService = $siteService;
    }

    public function setDataSource(WorkqueueDatasource $datasource): void
    {
        $this->datasource = $datasource;
    }

    public function setColumnCollection(ColumnCollection $columnCollection): void
    {
        $this->columnCollection = $columnCollection;
    }

    public function getWorkqueueData(int $offset = 0, int $limit = 10): array
    {
        $rawData = $this->datasource->getWorkqueueData($offset, $limit, $this->columnCollection);
        $result = [];
        foreach ($rawData['participant']['edges'] as $row) {
            $row = $row['node'];
            $temprow = [];
            foreach ($this->columnCollection as $column) {
                if ($column->getColumnDisplayed()) {
                    $temprow[] = $column->getColumnDisplay($row[$column->getDataField()], $row);
                }
            }
            $result['data'][] = $temprow;
        }
        $result['recordsTotal'] = $rawData['participant']['totalCount'];
        $result['recordsFiltered'] = $rawData['participant']['totalCount'];
        return $result;
    }

    public function loadWorkqueueColumns(string $workqueueProgram): void
    {
        try {
            $columnConfig = json_decode(file_get_contents(__DIR__ . '/../WorkQueue/ColumnDefs/' . $workqueueProgram . '/config.json'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \Exception('Unable to load column config');
        }
        $columns = [];
        foreach ($columnConfig['columns'] as $column) {
            if (!isset($column['class'])) {
                $columnClass = defaultColumn::class;
            } else {
                $columnClass = 'App\\WorkQueue\\ColumnDefs\\' . $workqueueProgram . '\\' . $column['class'];
            }
            $columnObject = new $columnClass($column);
            if (method_exists($columnObject, 'setRouteService')) {
                $columnObject->setRouteService($this->route);
            }
            if (method_exists($columnObject, 'setSiteService')) {
                $columnObject->setSiteService($this->siteService);
            }
            if ($columnObject->isEnabled()) {
                $columns[] = $columnObject;

                if ($column && isset($this->columnGroups[$columnObject->getColumnGroup()])) {
                    $this->columnGroups[$columnObject->getColumnGroup()]++;
                } else {
                    $this->columnGroups[$columnObject->getColumnGroup()] = 1;
                }
            }
        }

        $this->columnCollection = new ColumnCollection(...$columns);
    }

    public function getWorkqueueColumnHeaders(): array
    {
        $headers = [];
        foreach ($this->columnCollection as $column) {
            if ($column->getColumnDisplayed()) {
                $headers[] = $column->getColumnDisplayName();
            }
        }
        return $headers;
    }

    public function getColumnCollection(): ColumnCollection
    {
        return $this->columnCollection;
    }

    public function getColumnGroups(): array
    {
        return $this->columnGroups;
    }

    public function getSortableColumns(): array
    {
        $sortableColumns = [];
        foreach ($this->columnCollection as $column) {
            $sortableColumns[] = $column->isSortable();
        }
        return $sortableColumns;
    }
}
