<?php

namespace App\WorkQueue\ColumnDefs\NPH;

class module1ConsentStatus extends defaultColumn
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
                return "Consented<br>${latestTimestampString}";
            case 'DENY':
                return "Consented No<br>${latestTimestampString}";
            default:
                return 'Not Consented';
        }
        return $latestTimestampElement['value'] ?? '';
    }
}
