<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\columnInterface;

class defaultColumn implements columnInterface
{
    private bool $filterable = true;
    private bool $sortable = true;
    private bool $displayed = true;
    private string $workqueueField = '';
    private string $dataField = '';
    private string $displayName = '';
    private string $columnGroup = '';
    private bool $enabled = true;
    private array $config = [];

    public function __construct($config)
    {
        $this->loadConfig($config);
    }

    public function getColumnDisplay($data, $dataRow): string
    {
        return $data ?? '';
    }

    public function getColumnDisplayName(): string
    {
        return $this->displayName;
    }

    public function getColumnExport($data): string
    {
        return $data;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    public function setFilterData($filterData): void
    {
        // TODO: Implement setFilterData() method.
    }
    public function setSort($sort): void
    {
        // TODO: Implement setSort() method.
    }
    public function setColumnDisplayed(bool $columnDisplayed): void
    {
        $this->displayed = $columnDisplayed;
    }

    public function getColumnDisplayed(): bool
    {
        return $this->displayed;
    }
    public function getWorkqueueField(): string
    {
        return $this->workqueueField;
    }
    public function getDataField(): string
    {
        return $this->dataField;
    }
    private function loadConfig($config)
    {
        if (isset($config['filterable'])) {
            $this->filterable = $config['filterable'];
        }
        if (isset($config['sortable'])) {
            $this->sortable = $config['sortable'];
        }
        if (isset($config['dataField'])) {
            $this->dataField = $config['dataField'];
        }
        if (isset($config['workqueueField'])) {
            $this->workqueueField = $config['workqueueField'];
        }
        if (isset($config['display'])) {
            $this->displayName = $config['display'];
        }
        if (isset($config['group'])) {
            $this->columnGroup = $config['group'];
        }
        if (isset($config['enable'])) {
            $this->enabled = $config['enable'];
        }
        $this->config = $config;
    }

    public function setColumnGroup(string $columnGroup) : void{
          $this->columnGroup = $columnGroup;
    }

    public function getColumnGroup(): string
    {
        return $this->columnGroup;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
