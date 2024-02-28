<?php

namespace App\WorkQueue\ColumnDefs;

interface ColumnInterface
{
    public function getColumnDisplay($data, $dataRow): string;
    public function getColumnDisplayName(): string;
    public function getColumnExportHeaders(): array;
    public function getColumnExport($data, $dataRow): string;
    public function getColumnFilterType(): string;
    public function isFilterable(): bool;
    public function isSortable(): bool;
    public function isInDefaultGroup(): bool;
    public function isIncludeInAllGroups(): bool;
    public function setFilterData($filterData): void;
    public function setSort($sort): void;
    public function setColumnDisplayed(bool $columnDisplayed): void;
    public function getColumnDisplayed(): bool;
    public function getWorkqueueField(): string;
    public function getDataField(): string;
    public function getFilterData(): string;
    public function setColumnGroup(string $columnGroup): void;
    public function getColumnGroup(): string;
    public function isEnabled(): bool;
    public function getSortDirection(): string;
    public function setSortDirection(string $sortDirection): void;
    public function setSortOrder(int $sortOrder): void;
    public function getSortOrder(): int;
    public function getSortField(): string;
    public function setSortField(string $sortField): void;
}
