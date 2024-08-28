<?php

namespace App\Service;

use App\Audit\Log;
use App\Entity\BloodPressureDiastolicHeightPercentile;
use App\Entity\BloodPressureSystolicHeightPercentile;
use App\Entity\HeartRateAge;
use App\Entity\Measurement;
use App\Entity\MeasurementHistory;
use App\Entity\Site;
use App\Entity\User;
use App\Entity\ZScores;
use App\Service\Ppsc\PpscApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class MeasurementService
{
    protected $em;
    protected $requestStack;
    protected $userService;
    protected $rdrApiService;
    protected PpscApiService $ppscApiService;
    protected $siteService;
    protected $params;
    protected $measurement;
    protected $loggerService;

    public function __construct(
        EntityManagerInterface $em,
        RequestStack $requestStack,
        UserService $userService,
        RdrApiService $rdrApiService,
        PpscApiService $ppscApiService,
        SiteService $siteService,
        ParameterBagInterface $params,
        LoggerService $loggerService
    ) {
        $this->em = $em;
        $this->requestStack = $requestStack;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
        $this->ppscApiService = $ppscApiService;
        $this->siteService = $siteService;
        $this->params = $params;
        $this->loggerService = $loggerService;
    }

    public function load($measurement, $participant, $type = null)
    {
        $this->measurement = $measurement;
        $version = $this->getCurrentVersion($type);
        $measurement->setCurrentVersion($version);
        $this->loadFromAObject($measurement);
        if ($measurement->isPediatricForm()) {
            $growthChartsData = $this->getGrowthChartsData($participant->sexAtBirth, $participant->ageInMonths);
            $measurement->setGrowthCharts($growthChartsData);
            $measurement->setSexAtBirth($participant->sexAtBirth);
        }
    }

    public function loadFromAObject($measurement)
    {
        $this->measurement = $measurement;
        if (empty($measurement->getFinalizedUser())) {
            $finalizedUserId = $measurement->getFinalizedTs() ? $measurement->getUserId() : $this->userService->getUser()->getId();
            $finalizedUser = $this->em->getRepository(User::class)->findOneBy(['id' => $finalizedUserId]);
            $finalizedUserEmail = $finalizedUser->getEmail();
            $finalizedSite = $measurement->getFinalizedTs() || strpos($this->requestStack->getCurrentRequest()->get('_route'), 'read_') === 0 ? $measurement->getSite() : $this->requestStack->getSession()->get('site')->id;
        } else {
            $finalizedUserEmail = $measurement->getFinalizedUser()->getEmail();
            $finalizedSite = $measurement->getFinalizedSite();
        }
        $measurement->loadFromAObject($finalizedUserEmail, $finalizedSite);
    }

    public function createMeasurement($fhir)
    {
        try {
            $response = $this->ppscApiService->post('physical-measurements', $fhir);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->drcId)) {
                return $result->drcId;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getMeasurmeent($participantId, $measurementId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/Participant/{$participantId}/PhysicalMeasurements/{$measurementId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function requireBloodDonorCheck()
    {
        return $this->requestStack->getSession()->get('siteType') === 'dv' && ($this->siteService->isDiversionPouchSite() || $this->siteService->isBloodDonorPmSite());
    }

    public function getCurrentVersion($type)
    {
        if ($type === Measurement::BLOOD_DONOR && $this->requireBloodDonorCheck()) {
            return Measurement::BLOOD_DONOR_CURRENT_VERSION;
        }
        if (str_starts_with($type, 'peds-')) {
            return Measurement::CURRENT_VERSION . '-' . $type;
        }
        if ($this->requireEhrModificationProtocol()) {
            return Measurement::EHR_CURRENT_VERSION;
        }
        return Measurement::CURRENT_VERSION;
    }

    public function requireEhrModificationProtocol()
    {
        $sites = $this->em->getRepository(Site::class)->findOneBy([
            'deleted' => 0,
            'ehrModificationProtocol' => 1,
            'googleGroup' => $this->siteService->getSiteId()
        ]);
        if (!empty($sites)) {
            return true;
        }
        return false;
    }

    public function canEdit($measurementId, $participant)
    {
        // Allow cohort 1 and 2 participants to edit existing PMs even if status is false
        return !$participant->status && !empty($measurementId) ? $participant->editExistingOnly : $participant->status;
    }

    public function copyMeasurements($newMeasurement)
    {
        $newMeasurement->setParentId($this->measurement->getId());
        $newMeasurement->setFinalizedUser(null);
        $newMeasurement->setFinalizedSite(null);
        $newMeasurement->setFinalizedTs(null);
        $newMeasurement->setRdrId(null);
    }

    public function cancelRestoreRdrMeasurement($type, $reason)
    {
        $measurementRdrObject = $this->getCancelRestoreRdrObject($type, $reason);
        return $this->cancelRestoreMeasurement($type, $this->measurement->getParticipantId(), $this->measurement->getRdrId(), $measurementRdrObject);
    }

    public function getCancelRestoreRdrObject($type, $reason)
    {
        $obj = new \StdClass();
        $statusType = $type === Measurement::EVALUATION_CANCEL ? 'cancelled' : 'restored';
        $obj->status = $statusType;
        $obj->reason = $reason;
        $user = $this->userService->getUser()->getUsername();
        $site = $this->siteService->getSiteIdWithPrefix();
        $obj->{$statusType . 'Info'} = $this->getMeasurementUserSiteData($user, $site);
        return $obj;
    }

    public function cancelRestoreMeasurement($type, $participantId, $measurementId, $measurementJson)
    {
        try {
            $response = $this->ppscApiService->patch("physical-measurements/{$measurementId}", $measurementJson);
            $result = json_decode($response->getBody()->getContents());

            // Check RDR response
            $rdrStatus = $type === Measurement::EVALUATION_CANCEL ? Measurement::EVALUATION_CANCEL_STATUS : Measurement::EVALUATION_RESTORE_STATUS;
            if (is_object($result) && is_array($result->entry)) {
                foreach ($result->entry as $entries) {
                    if (strtolower($entries->resource->resourceType) === 'composition') {
                        return $entries->resource->status === $rdrStatus ? true : false;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function createMeasurementHistory($type, $measurementId, $reason = '')
    {
        $status = false;
        $connection = $this->em->getConnection();
        $connection->beginTransaction();
        try {
            $measurementHistory = new MeasurementHistory();
            $measurementHistory->setReason($reason);
            $measurementHistory->setMeasurement($this->measurement);
            $userRepository = $this->em->getRepository(User::class);
            $measurementHistory->setUser($userRepository->find($this->userService->getUser()->getId()));
            $measurementHistory->setSite($this->siteService->getSiteId());
            $measurementHistory->setType($type);
            $measurementHistory->setCreatedTs(new \DateTime());
            $this->em->persist($measurementHistory);
            $this->em->flush();
            $this->loggerService->log(
                Log::EVALUATION_HISTORY_CREATE,
                ['id' => $measurementHistory->getId(), 'type' => $measurementHistory->getType()]
            );

            // Update history id in measurement entity
            $this->measurement->setHistory($measurementHistory);
            $this->em->persist($this->measurement);
            $this->em->flush();
            $this->loggerService->log(Log::EVALUATION_EDIT, $this->measurement->getId());
            $connection->commit();
            $status = true;
        } catch (\Exception $e) {
            $connection->rollback();
        }
        return $status;
    }

    public function revertMeasurement($measurement)
    {
        try {
            $measurementId = $measurement->getId();
            $this->em->remove($measurement);
            $this->em->flush();
            $this->loggerService->log(Log::EVALUATION_DELETE, $measurementId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sendToRdr()
    {
        // Check if parent_id exists
        $parentRdrId = null;
        if ($this->measurement->getParentId()) {
            $parentMeasurement = $this->em->getRepository(Measurement::class)->findOneBy([
                'id' => $this->measurement->getParentId()
            ]);
            if (!empty($parentMeasurement)) {
                $parentRdrId = $parentMeasurement->getRdrId();
            }
        }
        $fhir = $this->measurement->getFhir($this->measurement->getFinalizedTs(), $parentRdrId);
        $rdrId = $this->createMeasurement($fhir);
        if (!empty($rdrId)) {
            $this->measurement->setRdrId($rdrId);
            $this->em->persist($this->measurement);
            $this->em->flush();
            return true;
        }
        return false;
    }

    public function getLastError()
    {
        return $this->rdrApiService->getLastError();
    }

    public function inactiveSiteFormDisabled(): bool
    {
        if ($this->measurement->getParentId()) {
            return false;
        }
        return !$this->siteService->isActiveSite();
    }

    protected function getMeasurementUserSiteData($user, $site)
    {
        return [
            'author' => [
                'system' => 'https://www.pmi-ops.org/healthpro-username',
                'value' => $user
            ],
            'site' => [
                'system' => 'https://www.pmi-ops.org/site-id',
                'value' => $site
            ]
        ];
    }

    private function getGrowthChartsData(string $sexAtBirth, int $ageInMonths): array
    {
        $growthCharts = $this->measurement->getGrowthChartsByAge($ageInMonths);
        return [
            'weightForAgeCharts' => $growthCharts['weightForAgeCharts'] ? $this->em->getRepository($growthCharts['weightForAgeCharts'])->getChartsData($sexAtBirth) : [],
            'weightForLengthCharts' => $growthCharts['weightForLengthCharts'] ? $this->em->getRepository($growthCharts['weightForLengthCharts'])->getChartsData($sexAtBirth) : [],
            'heightForAgeCharts' => $growthCharts['heightForAgeCharts'] ? $this->em->getRepository($growthCharts['heightForAgeCharts'])->getChartsData($sexAtBirth) : [],
            'headCircumferenceForAgeCharts' => $growthCharts['headCircumferenceForAgeCharts'] ? $this->em->getRepository($growthCharts['headCircumferenceForAgeCharts'])->getChartsData($sexAtBirth) : [],
            'bmiForAgeCharts' => $growthCharts['bmiForAgeCharts'] ? $this->em->getRepository($growthCharts['bmiForAgeCharts'])->getChartsData($sexAtBirth) : [],
            'bpSystolicHeightPercentileChart' => $this->em->getRepository(BloodPressureSystolicHeightPercentile::class)->getChartsData(),
            'bpDiastolicHeightPercentileChart' => $this->em->getRepository(BloodPressureDiastolicHeightPercentile::class)->getChartsData(),
            'heartRateAgeCharts' => $this->em->getRepository(HeartRateAge::class)->getChartsData(),
            'zScoreCharts' => $this->em->getRepository(ZScores::class)->getChartsData()
        ];
    }
}
