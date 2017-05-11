<?php
namespace Pmi\Service;

use Pmi\Mail\Message;
use Pmi\Audit\Log;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class WithdrawalService
{
    protected $app;
    protected $db;
    protected $rdr;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->em = $app['em'];
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
                $participants = [];
                foreach ($summaries as $summary) {
                    $participants[] = [
                        'id' => $summary->resource->participantId,
                        'withdrawalTime' => $summary->resource->withdrawalTime
                    ];
                }
                $organizationWithdrawals[$organization] = $participants;
            }
        }
        return $organizationWithdrawals;
    }

    protected function insertLogsRemoveDups(&$organizationWithdrawals, $email)
    {
        $insert = new \DateTime();
        foreach ($organizationWithdrawals as $organization => $participants) {
            foreach ($participants as $k => $participant) {
                $log = [
                    'participant_id' => $participant['id'],
                    'insert_ts' => $insert,
                    'withdrawal_ts' => new \DateTime($participant['withdrawalTime']),
                    'hpo_id' => $organization,
                    'email_notified' => $email
                ];
                try {
                    $this->em->getRepository('withdrawal_log')->insert($log);
                } catch (UniqueConstraintViolationException $e) {
                    // remove from if already notified
                    unset($organizationWithdrawals[$organization][$k]);
                }
                if (count($organizationWithdrawals[$organization]) === 0) {
                    // if all participants were removed (because already notified),
                    // remove the organization from the list
                    unset($organizationWithdrawals[$organization]);
                }
            }
        }
    }

    public function sendWithdrawalEmail()
    {
        $emailTo = $this->app->getConfig('withdrawal_notification_email');
        if (empty($emailTo)) {
            throw new \Exception('withdrawal_notification_email not defined');
        }
        $organizationWithdrawals = $this->getOrganizationWithdrawals();
        $this->insertLogsRemoveDups($organizationWithdrawals, $emailTo);
        if (count($organizationWithdrawals) === 0) {
            $this->app->log(Log::WITHDRAWAL_NOTIFY, 'Nothing to notify');
        } else {
            $message = new Message($this->app);
            $message
                ->setTo($emailTo)
                ->render('withdrawals', [
                    'organizationWithdrawals' => $organizationWithdrawals
                ])
                ->send();
            $counts = [];
            foreach ($organizationWithdrawals as $organization => $participants) {
                $counts[$organization] = count($participants);
            }
            $this->app->log(Log::WITHDRAWAL_NOTIFY, [
                'counts' => $counts,
                'notified' => $emailTo
            ]);
        }
    }
}
