<?php

namespace App\Service;

use GuzzleHttp\Exception\ClientException;

class DebugToolsService
{
    private $api;

    public function __construct(RdrApiService $api)
    {
        $this->api = $api;
    }

    public function getParticipantById($participantId)
    {
        try {
            $response = $this->api->get(sprintf('rdr/v1/Participant/%s/Summary', $participantId));
            return json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            return false;
        }
    }
}
