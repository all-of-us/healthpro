<?php

namespace App\Service;

use App\Service\Ppsc\PpscApiService;
use GuzzleHttp\Exception\ClientException;

class DebugToolsService
{
    private PpscApiService $api;

    public function __construct(PpscApiService $api)
    {
        $this->api = $api;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getParticipantById(string $participantId): ?array
    {
        try {
            return $this->api->getRawParticipantById($participantId);
        } catch (ClientException $e) {
            return null;
        }
    }
}
