<?php

namespace App\WorkQueue\ColumnDefs\NPH;

class EnrollmentStatus extends StatusField
{
    public function getColumnDisplay($data, $dataRow): string
    {
        $latestTimestampElement = UtilFunctions::getLatestTimestampElement($data);
        $latestTimestamp = new \DateTime($latestTimestampElement['time']);
        $latestTimestampString = $latestTimestamp->format('m/d/Y h:i A');
        switch ($latestTimestampElement['value']) {
            case 'nph_referred':
                return "Module 1 Referred<br>${latestTimestampString}";
            case 'module1_eligibilityConfirmed':
                return "Module 1 Eligibility Confirmed<br>${latestTimestampString}";
            case 'module1_eligibilityFailed':
                return "Module 1 Eligibility Failed<br>${latestTimestampString}";
            case 'module1_enrolled':
                return "Module 1 Enrolled<br>${latestTimestampString}";
            case 'module1_consented':
                return "Module 1 Consented<br>${latestTimestampString}";
        }
        return $latestTimestampElement['value'] ?? '';
    }
}
