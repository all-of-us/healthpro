<?php
namespace Pmi\Drc;

use Ramsey\Uuid\Uuid;

class RdrParticipants
{
    protected $rdrHelper;
    protected $client;
    protected static $resourceEndpoint = 'participant/v1/';

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
    }

    protected function getClient()
    {
        if (!is_object($this->client)) {
            $this->client = $this->rdrHelper->getClient(self::$resourceEndpoint);
        }
        return $this->client;
    }

    protected function participantToResult($participant)
    {
        if (!is_object($participant)) {
            return false;
        }
        if (!isset($participant->drc_internal_id)) {
            return false;
        }
        if (isset($participant->membership_tier) && $participant->membership_tier == 'CONSENTED') {
            $consentStatus = true;
        } else {
            $consentStatus = false;
        }
        switch ($participant->gender_identity) {
            case 'FEMALE':
                $gender = 'F';
                break;
            case 'MALE':
                $gender = 'M';
                break;
            default:
                $gender = 'U';
                break;
        }
        return (object)[
            'id' => $participant->drc_internal_id,
            'firstName' => $participant->first_name,
            'middleName' => $participant->middle_name,
            'lastName' => $participant->last_name,
            'dob' => new \DateTime($participant->date_of_birth),
            'gender' => $gender,
            'zip' => $participant->zip_code,
            'consentComplete' => $consentStatus
        ];
    }

    protected function paramsToQuery($params)
    {
        $query = [];
        if (isset($params['lastName'])) {
            $query['last_name'] = $params['lastName'];
        }
        if (isset($params['firstName'])) {
            $query['first_name'] = $params['firstName'];
        }
        if (isset($params['dob'])) {
            try {
                $date = new \DateTime($params['dob']);
                $query['date_of_birth'] = $date->format('Y-m-d');
            } catch (\Exception $e) {
                throw new Exception\InvalidDobException();
            }
        }

        return $query;
    }

    public function search($params)
    {
        $query = $this->paramsToQuery($params);
        try {
            $response = $this->getClient()->request('GET', 'participants', [
                'query' => $query
            ]);
        } catch (\Exception $e) {
            throw new Exception\FailedRequestException();
        }
        $responseObject = json_decode($response->getBody()->getContents());
        if (!is_object($responseObject)) {
            throw new Exception\InvalidResponseException();
        }
        if (!isset($responseObject->items) || !is_array($responseObject->items)) {
            return [];
        }
        $results = [];
        foreach ($responseObject->items as $participant) {
            $result = $this->participantToResult($participant);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    public function getById($id)
    {
        $memcache = new \Memcache();
        $memcacheKey = 'rdr_participant_' . $id;
        $participant = $memcache->get($memcacheKey);
        if (!$participant) {
            try {
                $response = $this->getClient()->request('GET', "participants/{$id}");
                $participant = json_decode($response->getBody()->getContents());
                $memcache->set($memcacheKey, $participant, 0, 300);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                return false;
            }
        }
        return $this->participantToResult($participant);
    }

    public function createParticipant($participant)
    {
        if (isset($participant['date_of_birth'])) {
            $dt = new \DateTime($participant['date_of_birth']);
            $participant['date_of_birth'] = $dt->format('Y-m-d');
        }
        $participant['biobank_id'] = Uuid::uuid4();
        try {
            $response = $this->getClient()->request('POST', 'participants', [
                'json' => $participant
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->biobank_id) && $result->biobank_id == $participant['biobank_id']) {
                return $result->drc_internal_id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function getEvaluation($participantId, $evaluationId)
    {
        try {
            $response = $this->getClient()->request('GET', "participants/{$participantId}/evaluations/{$evaluationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->evaluation_id)) {
                return $result;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function createEvaluation($participantId, $evaluation)
    {
        try {
            $response = $this->getClient()->request('POST', "participants/{$participantId}/evaluations", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->evaluation_id)) {
                return $result->evaluation_id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function updateEvaluation($participantId, $evaluationId, $evaluation)
    {
        try {
            $response = $this->getClient()->request('PUT', "participants/{$participantId}/evaluations/{$evaluationId}", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->evaluation_id)) {
                return $result->evaluation_id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
}
