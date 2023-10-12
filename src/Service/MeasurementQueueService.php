<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\Measurement;
use App\Entity\MeasurementQueue;
use App\Model\Measurement\Fhir;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class MeasurementQueueService
{
    protected $em;
    protected $loggerService;
    protected $params;
    protected $measurementService;
    protected $logger;
    protected ParticipantSummaryService $participantSummaryService;

    public function __construct(
        EntityManagerInterface $em,
        LoggerService $loggerService,
        ParameterBagInterface $params,
        MeasurementService $measurementService,
        LoggerInterface $logger,
        ParticipantSummaryService $participantSummaryService
    ) {
        $this->em = $em;
        $this->loggerService = $loggerService;
        $this->params = $params;
        $this->measurementService = $measurementService;
        $this->logger = $logger;
        $this->participantSummaryService = $participantSummaryService;
    }

    public function resendMeasurementsToRdr()
    {
        $limit = $this->params->get('evaluation_queue_limit');
        $measurementsQueue = $this->em->getRepository(MeasurementQueue::class)->findBy(['sentTs' => null, 'attemptedTs' => null], null, $limit);
        foreach ($measurementsQueue as $measurementQueue) {
            $measurementId = $measurementQueue->getEvaluationId();
            $measurement = $this->em->getRepository(Measurement::class)->find($measurementId);
            if (!$measurement) {
                continue;
            }
            $participant = $this->participantSummaryService->getParticipantById($measurement->getParticipantId());
            $this->measurementService->load($measurement, $participant);
            $fhir = $measurement->getFhir($measurement->getFinalizedTs(), $measurementQueue->getOldRdrId());
            $now = new \DateTime();
            if ($rdrMeasurementId = $this->measurementService->createMeasurement($measurement->getParticipantId(), $fhir)) {
                $measurement->setRdrId($rdrMeasurementId);
                $measurement->setFhirVersion(Fhir::CURRENT_VERSION);
                $this->em->persist($measurement);
                $this->em->flush();

                $measurementQueue->setNewRdrId($rdrMeasurementId);
                $measurementQueue->setFhirVersion(Fhir::CURRENT_VERSION);
                $measurementQueue->setSentTs($now);
                $measurementQueue->setAttemptedTs($now);
                $this->em->persist($measurementQueue);
                $this->em->flush();

                $this->loggerService->log(Log::QUEUE_RESEND_EVALUATION, [
                    'id' => $measurementQueue->getId(),
                    'old_rdr_id' => $measurementQueue->getOldRdrId(),
                    'new_rdr_id' => $rdrMeasurementId,
                    'fhir_version' => Fhir::CURRENT_VERSION
                ]);
            } else {
                $measurementQueue->setAttemptedTs($now);
                $this->em->persist($measurementQueue);
                $this->em->flush();
                $this->logger->error("#{$measurementId} failed sending to RDR: " . $this->measurementService->getLastError());
            }
        }
    }
}
