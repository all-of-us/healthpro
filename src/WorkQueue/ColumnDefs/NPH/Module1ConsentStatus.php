<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;

class Module1ConsentStatus extends DefaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = UtilFunctions::searchLatestTimestampElement($data, [$this->config['timestampField']]);
        if ($latestTimestampElement === null) {
            return "<i class='fas fa-times text-danger'></i> Not Completed<br>";
        }
        $latestTimestamp = new \DateTime($latestTimestampElement['time']);
        $latestTimestampString = $latestTimestamp->format('m/d/Y h:i A');
        switch ($latestTimestampElement['optIn']) {
            case 'PERMIT':
                return "<i class='fas fa-check text-success'></i> Consented<br>${latestTimestampString}";
            case 'DENY':
                return "<i class='fas fa-times text-danger'></i> Consented No<br>${latestTimestampString}";
            default:
                return $latestTimestampElement['optIn'];
        }
    }
}
