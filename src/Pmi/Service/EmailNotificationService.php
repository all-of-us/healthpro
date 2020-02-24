<?php

namespace Pmi\Service;

use Pmi\Mail\Message;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class EmailNotificationService
{
    protected $app;
    protected $db;
    protected $rdr;
    protected $type;
    protected $render;
    protected $time;
    protected $log;
    protected $statusText;

    public function __construct($app)
    {
        $this->app = $app;
        $this->db = $app['db'];
        $this->em = $app['em'];
        $this->rdr = $app['pmi.drc.participants'];
    }

    protected function getOrganizations()
    {
        $rows = $this->db->fetchAll('SELECT organization, GROUP_CONCAT(email) as emails FROM sites WHERE organization IS NOT NULL AND status = 1 GROUP BY organization');
        $organizations = [];
        $lastTypes = $this->getOrganizationsLastTypes();
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
                'emails' => $emails,
                'last' => isset($lastTypes[$row['organization']]) ? new \DateTime($lastTypes[$row['organization']]) : false
            ];
        }
        return $organizations;
    }

    protected function getOrganizationsLastTypes()
    {
        $rows = $this->db->fetchAll("SELECT hpo_id, max({$this->type}_ts) as ts FROM {$this->type}_log GROUP BY hpo_id");
        $lastTypes = [];
        foreach ($rows as $row) {
            $lastTypes[$row['hpo_id']] = $row['ts'];
        }
        return $lastTypes;
    }

    protected function getOrganizationTypes($id, $lastDeactivate)
    {
        $searchParams = $this->getSearchParams($id, $lastDeactivate);
        $participants = [];
        try {
            $summaries = $this->rdr->listParticipantSummaries($searchParams);
            foreach ($summaries as $summary) {
                $participants[] = [
                    'id' => $summary->resource->participantId,
                    'time' => $summary->resource->{$this->time}
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
                "{$this->type}_ts" => new \DateTime($participant['time']),
                'hpo_id' => $organization['id'],
                'email_notified' => implode(', ', $organization['emails'])
            ];
            try {
                $this->em->getRepository("{$this->type}_log")->insert($log);
            } catch (UniqueConstraintViolationException $e) {
                // remove from if already notified
                unset($participants[$k]);
            }
        }
    }

    public function sendEmails()
    {
        $organizations = $this->getOrganizations();
        foreach ($organizations as $organization) {
            $participants = $this->getOrganizationTypes($organization['id'], $organization['last']);
            $this->insertLogsRemoveDups($organization, $participants);
            if (count($participants) === 0) {
                $this->app->log($this->log, [
                    'org' => $organization['id'],
                    'status' => 'Nothing to notify'
                ]);
            } else {
                if (count($organization['emails']) === 0) {
                    $this->app->log($this->log, [
                        'org' => $organization['id'],
                        'status' => "{$this->statusText} participants but no one to notify",
                        'count' => count($participants)
                    ]);
                } else {
                    $message = new Message($this->app);
                    $message
                        ->setTo($organization['emails'])
                        ->render($this->render, [
                            'organization' => $organization['id']
                        ])
                        ->send();
                    $this->app->log($this->log, [
                        'org' => $organization['id'],
                        'status' => 'Notifications sent',
                        'count' => count($participants),
                        'notified' => $organization['emails']
                    ]);
                }
            }
        }
    }
}
