<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class MeasurementService
{
    const CURRENT_VERSION = '0.3.3';

    protected $em;
    protected $session;
    protected $loggerService;
    protected $userService;
    protected $rdrApiService;
    protected $version;
    protected $fieldData;
    protected $schema;
    protected $participant;
    protected $createdUser;
    protected $createdSite;
    protected $finalizedUserEmail;
    protected $finalizedSiteInfo;
    protected $locked = false;

    public function __construct(EntityManagerInterface $em, SessionInterface $session, LoggerService $loggerService, UserService $userService, RdrApiService $rdrApiService)
    {
        $this->em = $em;
        $this->session = $session;
        $this->loggerService = $loggerService;
        $this->userService = $userService;
        $this->rdrApiService = $rdrApiService;
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
}
