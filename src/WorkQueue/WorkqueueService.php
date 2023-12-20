<?php

namespace App\WorkQueue;

use App\WorkQueue\DataSources\WorkqueueDatasource;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WorkqueueService
{
    private WorkqueueDatasource $datasource;
    private ColumnCollection $columnCollection;
    private $columnGroups = [];
    private UrlGeneratorInterface $route;
    public function __construct(UrlGeneratorInterface $route)
    {
        $this->route = $route;
    }

    public function setDataSource(WorkqueueDatasource $datasource): void
    {
        $this->datasource = $datasource;
    }

    public function setColumnCollection(ColumnCollection $columnCollection): void
    {
        $this->columnCollection = $columnCollection;
    }

    public function getWorkqueueData(int $offset, int $limit): array
    {
        $rawData = $this->datasource->getWorkqueueData($offset, $limit, $this->columnCollection);
        $result = [];
        foreach ($rawData['participant']['edges'] as $row) {
            $row = $row['node'];
            $iterator = $this->columnCollection->getIterator();
            $temprow = [];
            while ($iterator->valid()) {
                $column = $iterator->current();
                if ($column->getColumnDisplayed()) {
                    $temprow[] = $column->getColumnDisplay($row[$column->getDataField()], $row);
                }
                $iterator->next();
            }
            $result[] = $temprow;
        }
        return $result;
    }

    public function loadWorkqueueColumns(string $workqueueProgram)
    {
        $columnConfig = json_decode(file_get_contents(__DIR__ . '/ColumnDefs/' . $workqueueProgram . '/config.json'), true);
        $columns = [];
        foreach ($columnConfig['columns'] as $index => $column) {
            if (!isset($column['class'])) {
                $columnClass = 'App\\WorkQueue\\ColumnDefs\\' . $workqueueProgram . '\\defaultColumn';
            } else {
                $columnClass = 'App\\WorkQueue\\ColumnDefs\\' . $workqueueProgram . '\\' . $column['class'];
            }
            $columnObject = new $columnClass($column);
            if (method_exists($columnObject, 'setRouteService')) {
                $columnObject->setRouteService($this->route);
            }
            $columns[] = $columnObject;
            if (isset($this->columnGroups[$columnObject->getColumnGroup()])) {
                $this->columnGroups[$columnObject->getColumnGroup()]++;
            } else {
                $this->columnGroups[$columnObject->getColumnGroup()] = 1;
            }
        }

        $this->columnCollection = new ColumnCollection(...$columns);
    }

    public function getWorkQueueGroups(): array
    {
        $groups = [];
        $iterator = $this->columnCollection->getIterator();
        while ($iterator->valid()) {
            $column = $iterator->current();
            if ($column->getColumnDisplayed()) {
                $groups[] = $column->getGroup()();
            }
            $iterator->next();
        }
        return $groups;
    }

    public function getWorkqueueColumnHeaders(): array
    {
        $headers = [];
        $iterator = $this->columnCollection->getIterator();
        while ($iterator->valid()) {
            $column = $iterator->current();
            $headers[] = $column->getColumnDisplayName();
            $iterator->next();
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
}
