<?php

namespace App\Service;


class DebugToolsService
{
    private $api;

    public function __construct(RdrApiService $api)
    {
        $this->api = $api;
    }

    public function getParticipantById($participantId)
    {
        $response = $this->api->get(sprintf('rdr/v1/Participant/%s/Summary', $participantId));
        return json_decode($response->getBody(), true);
    }
}
