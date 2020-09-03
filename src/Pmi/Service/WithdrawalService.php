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
}
