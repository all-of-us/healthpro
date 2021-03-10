<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class MeasurementService
{
    protected $em;
    protected $session;
    protected $userService;
    protected $rdrApiService;
    protected $siteService;

    public function __construct(
        EntityManagerInterface $em,
        SessionInterface $session,
        UserService $userService,
        RdrApiService $rdrApiService,
        SiteService $siteService
    ) {
        $this->em = $em;
        $this->session = $session;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
        $this->siteService = $siteService;

    }

    public function loadFromAObject($measurement)
    {
        if (empty($measurement->getFinalizedUser())) {
            $finalizedUserId = $measurement->getFinalizedTs() ? $measurement->getUserId() : $this->userService->getUser()->getId();
            $finalizedUserEmail = $this->em->getRepository(User::class)->findOneBy(['id' => $finalizedUserId]);
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
        return $this->session->get('siteType') === 'dv' && $this->siteService->isDiversionPouchSite();
    }
}
