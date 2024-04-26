<?php

namespace App\Helper;

class NphDietPeriodStatus
{
    public const NOT_STARTED = 'not_started';

    public static array $dietPeriodStatusMap = [
        'not_started' => ['text' => 'Not Started', 'textClass' => 'text-muted', 'badgeClass' => 'bg-secondary'],
        'in_progress_unfinalized' => ['text' => 'In Progress', 'textClass' => 'text-warning-orange', 'badgeClass' => 'bg-warning-orange'],
        'in_progress_finalized' => ['text' => 'In Progress', 'textClass' => 'text-warning-orange', 'badgeClass' => 'bg-warning-orange'],
        'in_progress_unfinalized_complete' => [
            'text' => 'Error',
            'textClass' => 'text-danger',
            'badgeClass' => 'bg-danger',
            'toolTipText' => 'This diet period was marked complete with unfinalized samples. Uncheck the sample processing complete box to aliquot and finalize all samples. For any samples that cannot be finalized, please cancel the sample(s). Samples left unfinalized are at risk of disposal.'
        ],
        'in_progress_finalized_complete' => ['text' => 'Complete', 'textClass' => 'text-success', 'badgeClass' => 'bg-success'],
    ];
}
