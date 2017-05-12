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
        $rows = $this->db->fetchAll('SELECT organization, GROUP_CONCAT(email) as emails FROM sites WHERE organization IS NOT NULL GROUP BY organization');
        $organizations = [];
        foreach ($rows as $row) {
            $emails = [];
            $list = explode(',', trim($row['emails']));
            foreach ($list as $email) {
                $email = trim(strtolower($email));
                if (!empty($email) && !in_array($email, $emails)) {
                    $emails[] = $email;
                }
            }
            $organizations[] = [
                'id' => $row['organization'],
                'emails' => $emails
            ];
        }
        return $organizations;
    }

    protected function getOrganizationWithdrawals($id)
    {
        $participants = [];
        $searchParams = [
            'withdrawalStatus' => 'NO_USE',
            'hpoId' => $id,
            '_sort:desc' => 'withdrawalTime'
        ];
        // TODO: filter by withdrawal time
        try {
            $summaries = $this->rdr->listParticipantSummaries($searchParams);
            foreach ($summaries as $summary) {
                $participants[] = [
                    'id' => $summary->resource->participantId,
                    'withdrawalTime' => $summary->resource->withdrawalTime
                ];
            }
        } catch (\Exception $e) {
            // RDR error already logged
        }
        return $participants;
    }

    protected function insertLogsRemoveDups($organization, &$participants)
    {
        $insert = new \DateTime();
        foreach ($participants as $k => $participant) {
            $log = [
                'participant_id' => $participant['id'],
                'insert_ts' => $insert,
                'withdrawal_ts' => new \DateTime($participant['withdrawalTime']),
                'hpo_id' => $organization['id'],
                'email_notified' => implode(', ', $organization['emails'])
            ];
            try {
                $this->em->getRepository('withdrawal_log')->insert($log);
            } catch (UniqueConstraintViolationException $e) {
                // remove from if already notified
                unset($participants[$k]);
            }
        }
    }

    public function sendWithdrawalEmails()
    {
        $organizations = $this->getOrganizations();
        foreach ($organizations as $organization) {
            $withdrawnParticipants = $this->getOrganizationWithdrawals($organization['id']);
            $this->insertLogsRemoveDups($organization, $withdrawnParticipants);
            if (count($withdrawnParticipants) === 0) {
                $this->app->log(Log::WITHDRAWAL_NOTIFY, [
                    'org' => $organization['id'],
                    'status' => 'Nothing to notify'
                ]);
            } else {
                if (count($organization['emails']) === 0) {
                    $this->app->log(Log::WITHDRAWAL_NOTIFY, [
                        'org' => $organization['id'],
                        'status' => 'Withdrawn participants but no one to notify',
                        'count' => count($withdrawnParticipants)
                    ]);
                } else {
                    $message = new Message($this->app);
                    $message
                        ->setTo($organization['emails'])
                        ->render('withdrawals', [
                            'organization' => $organization['id']
                        ])
                        ->send();
                    $this->app->log(Log::WITHDRAWAL_NOTIFY, [
                        'org' => $organization['id'],
                        'status' => 'Notifications sent',
                        'count' => count($withdrawnParticipants),
                        'notified' => $organization['emails']
                    ]);
                }
            }
        }
    }
}
