<?php

namespace App\Service;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

abstract class EmailNotificationService
{
    protected $em;
    protected $managerRegistry;
    protected $participantSummaryService;
    protected $loggerService;
    protected EnvironmentService $env;
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
    protected $deceasedStatus;
    protected $level;
    protected $levelField;
    protected $filterSummaries = false;
    protected $logEntity;

    public function getOrganizations()
    {
        $rows = $this->siteRepository->getOrganizations();
        $lastTypes = $this->getLatestOrganizationsFromLogs();
        return $this->getCustomArray($rows, $lastTypes);
    }

    public function getAwardees()
    {
        $rows = $this->siteRepository->getAwardees();
        $lastTypes = $this->getLatestAwardeesFromLogs();
        return $this->getCustomArray($rows, $lastTypes);
    }

    public function sendEmails()
    {
        $this->siteRepository->increaseGroupConcatMaxLength();
        $results = $this->getResults();
        foreach ($results as $result) {
            $participants = $this->getParticipants($result['id'], $result['last']);
            $this->insertLogsRemoveDups($result, $participants);
            if (count($participants) === 0) {
                $this->loggerService->log($this->log, [
                    'org' => $result['id'],
                    'status' => 'Nothing to notify'
                ]);
            } else {
                if (count($result['emails']) === 0) {
                    $this->loggerService->log($this->log, [
                        'org' => $result['id'],
                        'status' => "{$this->statusText} participants but no one to notify",
                        'count' => count($participants)
                    ]);
                } else {
                    if ($this->env->isStable() || $this->env->isProd()) {
                        $message = new Message($this->env, $this->loggerService, $this->twig, $this->params);
                        $message
                            ->setTo($result['emails'])
                            ->render($this->render, [
                                $this->level => $result['id'],
                                'participants' => $participants
                            ])
                            ->send();
                    }
                    $this->loggerService->log($this->log, [
                        'org' => $result['id'],
                        'status' => 'Notifications sent',
                        'count' => count($participants),
                        'notified' => $result['emails']
                    ]);
                }
            }
        }
    }

    abstract protected function getSearchParams($id, $lastDeactivate);

    protected function filterSummaries($summaries)
    {
        return $summaries;
    }

    protected function getLatestOrganizationsFromLogs()
    {
        $rows = $this->logRepository->getLatestOrganizations($this->deceasedStatus);
        $lastTypes = [];
        foreach ($rows as $row) {
            $lastTypes[$row['organizationId']] = $row['ts'];
        }
        return $lastTypes;
    }

    protected function getLatestAwardeesFromLogs()
    {
        $rows = $this->logRepository->getLatestAwardees();
        $lastTypes = [];
        foreach ($rows as $row) {
            $lastTypes[$row['awardeeId']] = $row['ts'];
        }
        return $lastTypes;
    }

    protected function getResults()
    {
        if ($this->level === 'awardee') {
            return $this->getAwardees();
        }
        return $this->getOrganizations();
    }


    protected function getCustomArray($rows, $lastTypes)
    {
        $data = [];
        foreach ($rows as $row) {
            $emails = [];
            $list = explode(',', trim($row['emails']));
            foreach ($list as $email) {
                $email = trim(strtolower($email));
                if (!empty($email) && !in_array($email, $emails)) {
                    $emails[] = $email;
                }
            }
            $data[] = [
                'id' => $row[$this->levelField],
                'emails' => $emails,
                'last' => isset($lastTypes[$row[$this->levelField]]) ? new \DateTime($lastTypes[$row[$this->levelField]]) : false
            ];
        }
        return $data;
    }

    protected function getParticipants($id, $latest)
    {
        $searchParams = $this->getSearchParams($id, $latest);
        $participants = [];
        try {
            $summaries = $this->participantSummaryService->listParticipantSummaries($searchParams);
            if ($this->filterSummaries) {
                $summaries = $this->filterSummaries($summaries);
            }
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

    protected function insertLogsRemoveDups($result, &$participants)
    {
        $insert = new \DateTime();
        foreach ($participants as $k => $participant) {
            try {
                $log = new $this->logEntity();
                $log->setParticipantId($participant['id']);
                $log->setInsertTs($insert);
                $log->{'set' . $this->type . 'Ts'}(new \DateTime($participant['time']));
                $log->{'set' . ucfirst($this->levelField)}($result['id']);
                $log->setEmailNotified(implode(', ', $result['emails']));
                if (!empty($this->status) && !empty($participant['status'])) {
                    $log->{'set' . $this->type . 'Status'}($participant['status']);
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
}
