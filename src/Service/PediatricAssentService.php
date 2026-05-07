<?php

namespace App\Service;

use App\Entity\PediatricAssent;
use Doctrine\ORM\EntityManagerInterface;

class PediatricAssentService
{
    private EntityManagerInterface $em;
    private UserService $userService;
    private SiteService $siteService;

    public function __construct(
        EntityManagerInterface $em,
        UserService $userService,
        SiteService $siteService
    ) {
        $this->em = $em;
        $this->userService = $userService;
        $this->siteService = $siteService;
    }

    /**
     * @return array{success: bool, errorMessage?: string, assent?: PediatricAssent}
     */
    public function submitMeasurementAssent(string $participantId, string $response, ?int $assentId = null): array
    {
        return $this->submitAssent($participantId, PediatricAssent::TYPE_PHYSICAL_MEASUREMENT, $response, $assentId);
    }

    /**
     * @return array{success: bool, errorMessage?: string, assent?: PediatricAssent}
     */
    public function submitOrderAssent(string $participantId, string $selection, string $response, ?int $assentId = null): array
    {
        $assentType = match ($selection) {
            'blood' => PediatricAssent::TYPE_BLOOD_SAMPLE,
            'saliva' => PediatricAssent::TYPE_SALIVA_SAMPLE,
            'urine' => PediatricAssent::TYPE_URINE_SAMPLE,
            default => null,
        };
        if ($assentType === null) {
            return [
                'success' => false,
                'errorMessage' => 'Invalid pediatric assent type.',
            ];
        }

        return $this->submitAssent($participantId, $assentType, $response, $assentId);
    }

    public function buildPediatricAssentPayload(PediatricAssent $assent): \stdClass
    {
        $createdTs = $assent->getCreatedTs();
        if (!$createdTs instanceof \DateTimeInterface) {
            throw new \RuntimeException('Pediatric assent created timestamp is missing.');
        }

        $createdUtc = \DateTimeImmutable::createFromInterface($createdTs)->setTimezone(new \DateTimeZone('UTC'));

        $payload = new \stdClass();
        $payload->participantId = $assent->getParticipantId();
        $payload->createdBy = $assent->getCreatedBy();
        $payload->site = $assent->getSite();
        $payload->assentType = $assent->getAssentType();
        $payload->assentResponse = $assent->getAssentResponse();
        $payload->created = $createdUtc->format('Y-m-d\TH:i:s\Z');

        return $payload;
    }

    /**
     * @return array{success: bool, errorMessage?: string, assent?: PediatricAssent}
     */
    private function submitAssent(string $participantId, string $assentType, string $response, ?int $assentId = null): array
    {
        $assentResponse = match ($response) {
            'yes' => PediatricAssent::RESPONSE_YES,
            'no' => PediatricAssent::RESPONSE_NO,
            'unable' => PediatricAssent::RESPONSE_UNABLE_TO_ASSENT,
            default => null,
        };
        if ($assentResponse === null) {
            return [
                'success' => false,
                'errorMessage' => 'Invalid pediatric assent response.',
            ];
        }

        $userEntity = $this->userService->getUserEntity();
        $createdBy = $userEntity?->getEmail() ?? $this->userService->getUser()?->getEmail();
        if (!$createdBy) {
            return [
                'success' => false,
                'errorMessage' => 'Unable to determine the current user for pediatric assent.',
            ];
        }
        $site = $this->siteService->getSiteId();
        if (!$site) {
            return [
                'success' => false,
                'errorMessage' => 'Unable to determine the current site for pediatric assent.',
            ];
        }

        $assent = null;
        if ($assentId !== null) {
            $existingAssent = $this->em->getRepository(PediatricAssent::class)->find($assentId);
            if (
                $existingAssent instanceof PediatricAssent &&
                $existingAssent->getParticipantId() === $participantId &&
                $existingAssent->getAssentType() === $assentType &&
                $existingAssent->getAssentResponse() === $assentResponse
            ) {
                if ($existingAssent->getApiStatus() === PediatricAssent::API_STATUS_CREATED) {
                    return [
                        'success' => true,
                        'assent' => $existingAssent,
                    ];
                }
                $assent = $existingAssent;
            }
        }

        if (!$assent instanceof PediatricAssent) {
            $assent = new PediatricAssent();
            $assent->setParticipantId($participantId);
            $assent->setUser($userEntity);
            $assent->setCreatedBy($createdBy);
            $assent->setSite($site);
            $assent->setAssentType($assentType);
            $assent->setAssentResponse($assentResponse);
            $assent->setCreatedTs(new \DateTime());
            $assent->setCreatedTimezoneId($userEntity?->getTimezoneId() ?? 2);
        }

        $assent->setApiAssentId(null);
        $assent->setApiStatus(PediatricAssent::API_STATUS_PENDING);
        $assent->setApiError(null);
        $this->em->persist($assent);
        $this->em->flush();

        return [
            'success' => true,
            'assent' => $assent,
        ];
    }
}
