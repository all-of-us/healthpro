<?php

namespace App\WorkQueue\ColumnDefs\NPH;

use App\WorkQueue\ColumnDefs\DefaultColumn;

class Module1HairAndNailOptIn extends DefaultColumn
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = UtilFunctions::searchLatestTimestampElement($data, ['m1_consent_tissue']);
        if ($latestTimestampElement === null) {
            return 'Not Consented';
        }
        $latestTimestamp = new \DateTime($latestTimestampElement['time']);
        $latestTimestampString = $latestTimestamp->format('m/d/Y h:i A');
        switch ($latestTimestampElement['optIn']) {
            case 'PERMIT':
                return "<i class='fas fa-check text-success'></i> Consented Hair and Nail<br>${latestTimestampString}";
            case 'PERMIT2':
                return "<i class='fas fa-check text-warning'></i> Consented Hair Only<br>${latestTimestampString}";
            case 'PERMIT3':
                return "<i class='fas fa-check text-warning'></i> Consented Nail Only<br>${latestTimestampString}";
            case 'DENY':
                return "<i class='fas fa-times text-danger'></i> Consented No<br>${latestTimestampString}";
            default:
                return 'Not Consented';
        }
    }
}
