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

    public function getDeactivateNotifications()
    {
        return $this->db->fetchAll('SELECT count(*) as count, insert_ts, hpo_id, email_notified as email FROM deactivate_log GROUP BY hpo_id, insert_ts, email_notified ORDER BY insert_ts DESC LIMIT 100');
    }
}
