<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\Order;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;

class HFHRepairService
{
    private EntityManagerInterface $em;
    private LoggerService $loggerService;

    public function __construct(EntityManagerInterface $em, LoggerService $logger)
    {
        $this->em = $em;
        $this->loggerService = $logger;
    }

    public function repairHFHParticipants(int $repairLimit = 100, $pariticpantId = null): void
    {
        $this->em->getConnection()->beginTransaction();
        $count = 0;
        $conn = $this->em->getConnection();
        if ($pariticpantId === null) {
            $sql = 'SELECT * FROM henry_ford_repair LIMIT 0, :repairLimit';
            $stmt = $conn->prepare($sql);
            $stmt->bindParam('repairLimit', $repairLimit, ParameterType::INTEGER);
        } else {
            $sql = 'SELECT * FROM henry_ford_repair WHERE participant_id = :participantId LIMIT 0, :repairLimit';
            $stmt = $conn->prepare($sql);
            $stmt->bindParam('repairLimit', $repairLimit, ParameterType::INTEGER);
            $stmt->bindParam('participantId', $pariticpantId, ParameterType::STRING);
        }
        $results = $stmt->executeQuery()->fetchAllAssociative();
        $deleteSql = 'DELETE FROM henry_ford_repair where id = :id';
        foreach ($results as $result) {
            $count++;
            try {
                $this->repairParticipantSite($result['participant_id'], $result['current_pairing_site'], $result['repair_site']);
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->bindParam('id', $result['id'], ParameterType::INTEGER);
                $deleteStmt->executeQuery();
            } catch (\Exception $exception) {
                $this->em->getConnection()->rollBack();
                $this->loggerService->log(Log::PROBLEM_NOTIFIY, $exception->getMessage());
                return;
            }
            if ($count === $repairLimit) {
                break;
            }
        }
        $this->em->flush();
        $this->em->clear();
        $this->em->getConnection()->commit();
    }

    private function repairParticipantSite(string $participantId, string $currentSite, string $repairSite): void
    {
        $repairSite = strtolower($repairSite);
        $repairSite = str_replace('hpo-site-', '', $repairSite);
        $currentSite = strtolower($currentSite);
        $currentSite = str_replace('hpo-site-', '', $currentSite);
        $evaluation = $this->em->getRepository(Measurement::class)->findBy(['participantId' => $participantId, 'finalizedSite' => $currentSite]);
        $orders = $this->em->getRepository(Order::class)->findBy(['participantId' => $participantId, 'finalizedSite' => $currentSite]);
        if (count($orders) == 0) {
            $this->loggerService->log(Log::PROBLEM_NOTIFIY, "No order found for participant $participantId with finalized site $currentSite");
        }
        foreach ($orders as $order) {
            $this->loggerService->log(Log::ORDER_EDIT, $order->getId());
            $order->setFinalizedSite($repairSite);
        }
        foreach ($evaluation as $measurement) {
            $this->loggerService->log(Log::EVALUATION_EDIT, $measurement->getId());
            $measurement->setFinalizedSite($repairSite);
        }
    }
}
