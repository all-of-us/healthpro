<?php
namespace Pmi\Service;

use Pmi\Mail\Message;

class WithdrawalService
{
    protected $app;
    protected $db;
    protected $rdr;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->rdr = $app['pmi.drc.participants'];
    }

    protected function getOrganizations()
    {
        $rows = $this->db->fetchAll('SELECT distinct organization FROM sites where organization is not null');
        $organizations = [];
        foreach ($rows as $row) {
            $organizations[] = $row['organization'];
        }
        return $organizations;
    }

    protected function getOrganizationWithdrawals()
    {
        $organizationWithdrawals = [];
        foreach ($this->getOrganizations() as $organization) {
            $searchParams = [
                'withdrawalStatus' => 'NO_USE',
                'hpoId' => $organization,
                '_sort:desc' => 'withdrawalTime'
            ];

            // TODO: filter by withdrawal time
            $summaries = $this->rdr->listParticipantSummaries($searchParams);
            if (count($summaries) > 0) {
                $participantIds = [];
                foreach ($summaries as $summary) {
                    // TODO: check log
                    $participantIds[] = $summary->resource->participantId;
                    // TODO: insert into log
                }
                $organizationWithdrawals[$organization] = $participantIds;
            }
        }
        return $organizationWithdrawals;
    }

    public function sendWithdrawalEmail()
    {
        $emailTo = $this->app->getConfig('withdrawal_notification_email');
        if (empty($emailTo)) {
            throw new \Exception('withdrawal_notification_email not defined');
        }
        $organizationWithdrawals = $this->getOrganizationWithdrawals();
        $message = new Message($this->app);
        $message
            ->setTo($emailTo)
            ->render('withdrawals', [
                'organizationWithdrawals' => $organizationWithdrawals
            ])
            ->send();
    }
}
