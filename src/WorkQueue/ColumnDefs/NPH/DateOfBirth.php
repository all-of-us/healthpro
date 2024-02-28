<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;

class DateOfBirth extends DefaultColumn
{
    public function setFilterData($filterData): void
    {
        parent::setFilterData($filterData);
    }

    public function getColumnDisplay($data, $dataRow): string
    {
        if ($data === 'UNSET') {
            return '';
        }
        $date = new \DateTime($data);
        return $date->format('m/d/Y');
    }
}
