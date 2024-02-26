<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;

class DateOfBirth extends DefaultColumn
{
    public function setFilterData($filterData): void
    {
        parent::setFilterData($filterData);
    }
}
