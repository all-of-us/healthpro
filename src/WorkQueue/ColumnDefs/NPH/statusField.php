<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use DateTime;

class statusField extends defaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = utilFunctions::getLatestTimestampElement($data);
        return $latestTimestampElement['value'] ?? '';
    }
}
