<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\NPH\defaultColumn;

class module1HairAndNailOptIn extends defaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = utilFunctions::searchLatestTimestampElement($data, ['m1_consent_tissue']);
        if ($latestTimestampElement === null) {
            return 'Not Consented';
        }
        $latestTimestamp = new \DateTime($latestTimestampElement['time']);
        $latestTimestampString = $latestTimestamp->format('m/d/Y h:i A');
        switch ($latestTimestampElement['optIn']) {
            case 'PERMIT':
                return "Consented Hair and Nail<br>${latestTimestampString}";
            case 'PERMIT2':
                return "Consented Hair Only<br>${latestTimestampString}";
            case 'PERMIT3':
                return "Consented Nail Only<br>${latestTimestampString}";
            case 'DENY':
                return "Consented No<br>${latestTimestampString}";
            default:
                return 'Not Consented';
        }
    }
}
