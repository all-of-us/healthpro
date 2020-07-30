<?php

namespace App\Service;

use App\Service\RdrApiService;

class ParticipantSummaryService
{
    protected $api;

    public function __construct(RdrApiService $api)
    {
        $this->api = $api;
    }

    public function getParticipantById($participantId)
    {
        try {
            $response = $this->api->get(sprintf('rdr/v1/Participant/%s/Summary', $participantId));
            return json_decode($response->getBody());
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return false;
        }
    }
}
