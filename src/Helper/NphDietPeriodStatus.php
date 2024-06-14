<?php

namespace App\Helper;

class NphDietPeriodStatus
{
    public const NOT_STARTED = 'not_started';
    public const ERROR_IN_PROGRESS_UNFINALIZED_COMPLETE = 'error_in_progress_unfinalized_complete';
    public const IN_PROGRESS_UNFINALIZED = 'in_progress_unfinalized';
    public const IN_PROGRESS_FINALIZED = 'in_progress_finalized';
    public const IN_PROGRESS_FINALIZED_COMPLETE = 'in_progress_finalized_complete';
    public const ERROR_NEXT_DIET_STARTED = 'error_next_diet_started';
    public const ERROR_NEXT_DIET_STARTED_FINALIZED = 'error_next_diet_started_finalized';
    public const ERROR_NEXT_MODULE_STARTED = 'error_next_module_started';

    public static array $dietPeriodStatusMap = [
        'not_started' => [
            'text' => 'Not Started',
            'textClass' => 'text-muted',
            'badgeClass' => 'bg-secondary',
            'badgeIcon' => 'fa-minus-circle',
            'statusIcon' => 'fa-minus-circle'
        ],
        'in_progress_unfinalized' => [
            'text' => 'In Progress',
            'textClass' => 'text-warning-orange',
            'badgeClass' => 'bg-warning-orange',
            'badgeIcon' => 'fa-sync-alt',
            'statusIcon' => 'fa-sync-alt'
        ],
        'in_progress_finalized' => [
            'text' => 'In Progress',
            'textClass' => 'text-warning-orange',
            'badgeClass' => 'bg-warning-orange',
            'badgeIcon' => 'fa-sync-alt',
            'statusIcon' => 'fa-sync-alt'
        ],
        'error_in_progress_unfinalized_complete' => [
            'text' => 'Error',
            'textClass' => 'text-danger',
            'badgeClass' => 'bg-danger',
            'toolTipStatus' => 'complete_unfinalized',
            'badgeIcon' => 'fa-times',
            'statusIcon' => 'fa-times-circle',
            'dietCardClass' => 'bg-success-subtle'
        ],
        'in_progress_finalized_complete' => [
            'text' => 'Complete',
            'textClass' => 'text-success',
            'badgeClass' => 'bg-success',
            'badgeIcon' => 'fa-check-circle',
            'statusIcon' => 'fa-check-circle',
            'dietCardClass' => 'bg-success-subtle'
        ],
        'error_next_diet_started' => [
            'text' => 'Error',
            'textClass' => 'text-danger',
            'badgeClass' => 'bg-danger',
            'toolTipStatus' => 'error_next_diet_started',
            'badgeIcon' => 'fa-times',
            'statusIcon' => 'fa-times-circle'
        ],
        'error_next_diet_started_finalized' => [
            'text' => 'Error',
            'textClass' => 'text-danger',
            'badgeClass' => 'bg-danger',
            'toolTipStatus' => 'error_next_diet_started',
            'badgeIcon' => 'fa-times',
            'statusIcon' => 'fa-times-circle'
        ],
        'error_next_module_started' => [
            'text' => 'Error',
            'textClass' => 'text-danger',
            'badgeClass' => 'bg-danger',
            'toolTipStatus' => 'error_next_module_started',
            'badgeIcon' => 'fa-times',
            'statusIcon' => 'fa-times-circle'
        ],
    ];

    public static array $dietToolTipMessages = [
        'complete_unfinalized' => 'This diet period was marked complete with unfinalized samples. Uncheck the sample processing complete box to aliquot and finalize all samples. For any samples that cannot be finalized, please cancel the sample(s). Samples left unfinalized are at risk of disposal.',
        'complete_unfinalized_1' => 'This module was marked complete with unfinalized samples. Uncheck the sample processing complete box to aliquot and finalize all samples. For any samples that cannot be finalized, please cancel the sample(s). Samples left unfinalized are at risk of disposal.',
        'error_next_module_started_1' => 'This module has not been marked as complete, but the next module has already been started. Please finalize or cancel samples within this module and mark as complete. Samples left unfinalized are at risk of disposal.',
        'error_next_diet_started' => 'This diet period has not been marked as complete, but the next diet period has already been started. Please finalize or cancel samples within this diet period and mark as complete. Samples left unfinalized are at risk of disposal.'
    ];
}
