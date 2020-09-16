<?php

namespace App\Service;

use App\Entity\DeceasedLog;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class EmailNotificationService
{
    protected $em;
    protected $managerRegistry;
    protected $participantSummaryService;
    protected $loggerService;
    protected $env;
    protected $params;
    protected $twig;
    protected $siteRepository;
    protected $logRepository;
    protected $type;
    protected $render;
    protected $time;
    protected $log;
    protected $statusText;
    protected $status;

    public function getOrganizations()
    {
        $rows = $this->siteRepository->getOrganizations();
        $organizations = [];
        $lastTypes = $this->getLatestOrganizationsFromLogs();
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

    protected function getLatestOrganizationsFromLogs()
    {
        $rows = $this->logRepository->getLatestOrganizations();
        $lastTypes = [];
        foreach ($rows as $row) {
            $lastTypes[$row['hpoId']] = $row['ts'];
        }
        return $lastTypes;
    }

    protected function getOrganizationTypes($id, $latestOrganization)
    {
        $searchParams = $this->getSearchParams($id, $latestOrganization);
        $participants = [];
        try {
            $summaries = $this->participantSummaryService->listParticipantSummaries($searchParams);
            foreach ($summaries as $summary) {
                $results = [
                    'id' => $summary->resource->participantId,
                    'time' => $summary->resource->{$this->time}
                ];
                if (!empty($this->status)) {
                    $results['status'] = $summary->resource->{$this->status};
                }
                $participants[] = $results;

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
            try {
                $log = new DeceasedLog();
                $log->setParticipantId($participant['id']);
                $log->setInsertTs($insert);
                $log->setDeceasedTs(new \DateTime($participant['time']));
                $log->setHpoId($organization['id']);
                $log->setEmailNotified(implode(', ', $organization['emails']));
                if (!empty($participant['status'])) {
                    $log->setDeceasedStatus($participant['status']);
                }
                $this->em->persist($log);
                $this->em->flush();
            } catch (UniqueConstraintViolationException $e) {
                // remove from if already notified
                unset($participants[$k]);
                // Entity managers gets closed on UniqueConstraintViolationException so reset it
                $this->managerRegistry->resetManager();
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
                $this->loggerService->log($this->log, [
                    'org' => $organization['id'],
                    'status' => 'Nothing to notify'
                ]);
            } else {
                if (count($organization['emails']) === 0) {
                    $this->loggerService->log($this->log, [
                        'org' => $organization['id'],
                        'status' => "{$this->statusText} participants but no one to notify",
                        'count' => count($participants)
                    ]);
                } else {
                    $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
                    $message
                        ->setTo($organization['emails'])
                        ->render($this->render, [
                            'organization' => $organization['id'],
                            'participants' => $participants
                        ])
                        ->send();
                    $this->loggerService->log($this->log, [
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
