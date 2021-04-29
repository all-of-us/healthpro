<?php

namespace App\Service;

use App\Entity\Measurement;
use App\Entity\Site;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class MeasurementService
{
    protected $em;
    protected $session;
    protected $userService;
    protected $rdrApiService;
    protected $siteService;
    protected $params;
    protected $measurement;

    public function __construct(
        EntityManagerInterface $em,
        SessionInterface $session,
        UserService $userService,
        RdrApiService $rdrApiService,
        SiteService $siteService,
        ParameterBagInterface $params
    ) {
        $this->em = $em;
        $this->session = $session;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;
        $this->params = $params;

    }

    public function load($measurement, $type)
    {
        $this->measurement = $measurement;
        $version = $this->getCurrentVersion($type);
        $measurement->setCurrentVersion($version);
        $this->loadFromAObject($measurement);
    }

    public function loadFromAObject($measurement)
    {
        $this->measurement = $measurement;
        if (empty($measurement->getFinalizedUser())) {
            $finalizedUserId = $measurement->getFinalizedTs() ? $measurement->getUserId() : $this->userService->getUser()->getId();
            $finalizedUser = $this->em->getRepository(User::class)->findOneBy(['id' => $finalizedUserId]);
            $finalizedUserEmail = $finalizedUser->getEmail();
            $finalizedSite = $measurement->getFinalizedTs() ? $measurement->getSite() : $this->session->get('site')->id;
        } else {
            $finalizedUserEmail = $measurement->getFinalizedUser()->getEmail();
            $finalizedSite = $measurement->getFinalizedSite();
        }
        $measurement->loadFromAObject($finalizedUserEmail, $finalizedSite);
    }

    public function createMeasurement($participantId, $fhir)
    {
        try {
            $response = $this->rdrApiService->post("rdr/v1/Participant/{$participantId}/PhysicalMeasurements", $fhir);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function requireBloodDonorCheck()
    {
        return $this->params->has('feature.blooddonorpm') && $this->params->get('feature.blooddonorpm') && $this->session->get('siteType') === 'dv' && $this->siteService->isDiversionPouchSite();
    }

    public function getCurrentVersion($type)
    {
        if ($type === Measurement::BLOOD_DONOR && $this->requireBloodDonorCheck()) {
            return Measurement::BLOOD_DONOR_CURRENT_VERSION;
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

    public function canEdit($evalId, $participant)
    {
        // Allow cohort 1 and 2 participants to edit existing PMs even if status is false
        return !$participant->status && !empty($evalId) ? $participant->editExistingOnly : $participant->status;

    }
}
