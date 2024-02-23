<?php

namespace App\WorkQueue\ColumnDefs;

class DefaultColumn implements ColumnInterface
{
    protected array $config = [];
    private bool $filterable = true;
    private bool $sortable = true;
    private bool $displayed = true;
    private string $workqueueField = '';
    private string $dataField = '';
    private string $displayName = '';
    private string $columnGroup = '';
    private bool $includeInAllGroups = false;
    private bool $defaultGroup = false;
    private bool $enabled = true;
    private string $filterData = '';
    private string $columnFilterType = '';
    private string $sortDirection = '';
    private int $sortOrder = -1;
    private string $sortField = '';

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

    public function getColumnFilterType(): string
    {
        return $this->columnFilterType;
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
        $this->filterData = $filterData;
    }

    public function getFilterData(): string
    {
        return $this->filterData;
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

    public function setColumnGroup(string $columnGroup): void
    {
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

    public function isIncludeInAllGroups(): bool
    {
        return $this->includeInAllGroups;
    }

    public function isInDefaultGroup(): bool
    {
        return $this->defaultGroup;
    }

    public function setSortDirection($sortDirection): void
    {
        $this->sortDirection = $sortDirection;
    }

    public function setSortOrder($sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
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
        if (isset($config['includeInAllGroups'])) {
            $this->includeInAllGroups = $config['includeInAllGroups'];
        }
        if (isset($config['defaultGroup'])) {
            $this->defaultGroup = $config['defaultGroup'];
        }
        if (isset($config['filterType'])) {
            $this->columnFilterType = $config['filterType'];
        }
        if (isset($config['sortField'])) {
            $this->sortField = $config['sortField'];
        }
        $this->config = $config;
    }

    public function getSortField(): string
    {
        return $this->sortField;
    }

    public function setSortField(string $sortField): void
    {
        $this->sortField = $sortField;
    }
}
