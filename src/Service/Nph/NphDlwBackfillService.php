<?php

namespace App\Service\Nph;

use App\Audit\Log;
use App\Entity\NphDlw;
use App\Service\LoggerService;
use App\Service\RdrApiService;
use Doctrine\ORM\EntityManagerInterface;

class NphDlwBackfillService
{
    protected EntityManagerInterface $em;
    protected LoggerService $loggerService;
    protected RdrApiService $rdrApiService;

    public function __construct(EntityManagerInterface $em, LoggerService $loggerService, RdrApiService $rdrApiService)
    {
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->rdrApiService = $rdrApiService;
    }

    public function backfillNphDlw(): void
    {
        $dlws = $this->em->getRepository(NphDlw::class)->getDlwWithMissingRdrId(25);
        foreach ($dlws as $dlw) {
            $this->resyncDlwCollectionWithRDR($dlw);
        }
    }

    public function resyncDlwCollectionWithRDR(NphDlw $dlw)
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $this->sendDLWToRdr($dlw);
            $this->loggerService->log(Log::NPH_DLW_RDR_BACKLOG_SUCCESS, $dlw->getId());
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->loggerService->log(Log::NPH_DLW_RDR_BACKLOG_FAILURE, $e->getMessage());
            $this->loggerService->log(Log::NPH_DLW_RDR_BACKLOG_FAILURE, $dlw->getId());
        }
        $connection->commit();
    }

    private function sendDLWToRdr(NphDlw $dlw): void
    {
        $dlwRDRObject = $this->getDLWRDRObject($dlw);
        if ($dlw->getRdrId()) {
            $this->editRDRDlw($dlw->getNphParticipant(), $dlw->getRdrId(), $dlwRDRObject);
            $this->loggerService->log(Log::NPH_DLW_RDR_CREATE, $dlw->getRdrId());
        } else {
            $rdrId = $this->createRDRDlw($dlw->getNphParticipant(), $dlwRDRObject);
            $dlw->setRdrId($rdrId);
            $this->em->persist($dlw);
            $this->em->flush();
            $this->loggerService->log(Log::NPH_DLW_RDR_UPDATE, $dlw->getRdrId());
        }
    }

    private function editRDRDlw(string $participantId, string $rdrId, \stdClass $rdrDlwObject): void
    {
        try {
            $response = $this->rdrApiService->put(
                "rdr/v1/api/v1/nph/Participant/{$participantId}/DlwDosage/{$rdrId}",
                $rdrDlwObject
            );
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
        }
    }

    private function createRDRDlw(string $participantId, \stdClass $rdrDlwObject): ?string
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/api/v1/nph/Participant/{$participantId}/DlwDosage", $rdrDlwObject);
            $result = json_decode($response->getBody()->getContents());
            if ($result) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return null;
        }
        return null;
    }

    private function getDLWRDRObject(NphDlw $dlw): \stdClass
    {
        $object = new \stdClass();
        $object->module = $dlw->getModule();
        $object->visitperiod = $dlw->getVisitPeriod();
        $object->batchid = $dlw->getDoseBatchId();
        $object->participantweight = $dlw->getParticipantWeight();
        $object->dose = $dlw->getActualDose();
        $object->calculateddose = $dlw->getParticipantWeight() * 1.5;
        $object->dosetime = $dlw->getDoseAdministered()->format('Y-m-d\TH:i:s\Z');
        return $object;
    }
}
