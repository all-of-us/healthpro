<?php

namespace App\Service;

use App\Service\RdrApiService;
use Pmi\Drc\Exception\FailedRequestException;
use Pmi\Drc\Exception\InvalidResponseException;

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

    public function listParticipantSummaries($params)
    {
        try {
            $response = $this->api->get('rdr/v1/ParticipantSummary', [
                'query' => $params
            ]);
        } catch (\Exception $e) {
            throw $e;
            throw new FailedRequestException();
        }

        $contents = $response->getBody()->getContents();
        $responseObject = json_decode($contents);
        if (!is_object($responseObject)) {
            throw new InvalidResponseException();
        }
        if (!isset($responseObject->entry) || !is_array($responseObject->entry)) {
            return [];
        }
        return $responseObject->entry;
    }
}
