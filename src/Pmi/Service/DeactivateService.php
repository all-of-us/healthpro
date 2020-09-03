<?php

namespace Pmi\Service;

use Pmi\Audit\Log;

class DeactivateService extends EmailNotificationService
{
    protected $type = 'deactivate';
    protected $render = 'deactivates';
    protected $time = 'suspensionTime';
    protected $log = Log::DEACTIVATE_NOTIFY;
    protected $statusText = 'deactivated';

    protected function getSearchParams($id, $lastDeactivate)
    {
        $searchParams = [
            'withdrawnStatus' => 'NOT_WITHDRAWN',
            'suspensionStatus' => 'NO_CONTACT',
            'hpoId' => $id,
            '_sort:desc' => 'suspensionTime'
        ];
        if ($lastDeactivate) {
            $filterTime = clone $lastDeactivate;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['suspensionTime'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }
}
