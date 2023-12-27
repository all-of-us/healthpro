<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\defaultColumn;

class moduleConsentStatus extends defaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = utilFunctions::searchLatestTimestampElement($data, ['m1_consent']);
        if ($latestTimestampElement === null) {
            return 'Not Consented';
        }
        $latestTimestamp = new \DateTime($latestTimestampElement['time']);
        $latestTimestampString = $latestTimestamp->format('m/d/Y h:i A');
        switch ($latestTimestampElement['optIn']) {
            case 'PERMIT':
                return "<i class='fas fa-check text-success'></i> Consented<br>${latestTimestampString}";
            case 'DENY':
                return "<i class='fas fa-times text-danger'></i> Consented No<br>${latestTimestampString}";
            default:
                return "<i class='fas fa-times text-danger'></i> Not Consented";
        }
    }
}
