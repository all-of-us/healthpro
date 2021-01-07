<?php

namespace App\Service;


class PatientStatusService
{
    protected $rdrApiService;

    public function __construct(RdrApiService $rdrApiService)
    {
        $this->rdrApiService = $rdrApiService;
    }

    public function getPatientStatus($participantId, $organizationId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/PatientStatus/{$participantId}/Organization/{$organizationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

    public function getPatientStatusHistory($participantId, $organizationId)
    {
        try {
            $response = $this->rdrApiService->get("rdr/v1/PatientStatus/{$participantId}/Organization/{$organizationId}/History");
            $result = json_decode($response->getBody()->getContents());
            if (is_array($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrApiService->logException($e);
            return false;
        }
        return false;
    }

}
