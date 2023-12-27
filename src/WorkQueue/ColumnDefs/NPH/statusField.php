<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\defaultColumn;

class statusField extends defaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = utilFunctions::getLatestTimestampElement($data);
        return $latestTimestampElement['value'] ?? '';
    }
}
