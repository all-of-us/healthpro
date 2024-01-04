<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;

class StatusField extends DefaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = UtilFunctions::getLatestTimestampElement($data);
        return $latestTimestampElement['value'] ?? '';
    }
}
