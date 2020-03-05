<?php

namespace Pmi\Service;

use Pmi\Audit\Log;

class WithdrawalService extends EmailNotificationService
{
    protected $type = 'withdrawal';
    protected $render = 'withdrawals';
    protected $time = 'withdrawalTime';
    protected $log = Log::WITHDRAWAL_NOTIFY;
    protected $statusText = 'withdrawn';

    protected function getSearchParams($id, $lastWithdrawal)
    {
        $searchParams = [
            'withdrawalStatus' => 'NO_USE',
            'hpoId' => $id,
            '_sort:desc' => 'withdrawalTime'
        ];
        if ($lastWithdrawal) {
            $filterTime = clone $lastWithdrawal;
            // Go back 1 day to make sure no participants are missed
            $filterTime->sub(new \DateInterval('P1D'));
            $searchParams['withdrawalTime'] = 'ge' . $filterTime->format('Y-m-d\TH:i:s');
        }
        return $searchParams;
    }

    public function getWithdrawalNotifications()
    {
        return $this->db->fetchAll('SELECT count(*) as count, insert_ts, hpo_id, email_notified as email FROM withdrawal_log GROUP BY hpo_id, insert_ts, email_notified ORDER BY insert_ts DESC LIMIT 100');
    }
}
