<?php
namespace Pmi\Drc;

use Pmi\Entities\Participant;
use Ramsey\Uuid\Uuid;

class RdrParticipants
{
    protected $rdrHelper;
    protected $client;
    protected static $resourceEndpoint = 'rdr/v1/';

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
        if (isset($participant->participantId)) {
            $id = $participant->participantId;
        } else {
            return false;
        }
        if (!empty($participant->biobankId)) {
            $biobankId = $participant->biobankId;
        } else {
            return false;
        }
        if (isset($participant->membershipTier) && in_array($participant->membershipTier, ['VOLUNTEER', 'ENROLLEE', 'FULL_PARTICIPANT'])) {
            $consentStatus = true;
        } else {
            $consentStatus = true; // TODO - not sure where we will end up with membership tier. For now, treat all as consented
        }
        switch ($participant->genderIdentity) {
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
        $genderIdentity = str_replace('_', ' ', $participant->genderIdentity);
        $genderIdentity = ucfirst(strtolower($genderIdentity));
        return new Participant([
            'id' => $id,
            'biobankId' => $biobankId,
            'firstName' => $participant->firstName,
            'middleName' => $participant->middleName,
            'lastName' => $participant->lastName,
            'dob' => new \DateTime($participant->dateOfBirth),
            'genderIdentity' => $genderIdentity,
            'gender' => $gender,
            'zip' => $participant->zipCode,
            'consentComplete' => $consentStatus
        ]);
    }

    protected function paramsToQuery($params)
    {
        $query = [];
        if (isset($params['lastName'])) {
            $query['lastName'] = ucfirst($params['lastName']);
        }
        if (isset($params['firstName'])) {
            $query['firstName'] = ucfirst($params['firstName']);
        }
        if (isset($params['dob'])) {
            try {
                $date = new \DateTime($params['dob']);
                $query['dateOfBirth'] = $date->format('Y-m-d');
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
            $response = $this->getClient()->request('GET', 'ParticipantSummary', [
                'query' => $query
            ]);
        } catch (\Exception $e) {
            throw new Exception\FailedRequestException();
        }
        $responseObject = json_decode($response->getBody()->getContents());

        if (!is_object($responseObject)) {
            throw new Exception\InvalidResponseException();
        }
        if (!isset($responseObject->entry) || !is_array($responseObject->entry)) {
            return [];
        }
        $results = [];
        foreach ($responseObject->entry as $participant) {
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
                $response = $this->getClient()->request('GET', "Participant/{$id}/Summary");
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
        try {
            $response = $this->getClient()->request('POST', 'Participant', [
                'json' => $participant
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && (isset($result->drc_internal_id) || isset($result->participant_id))) {
                return isset($result->drc_internal_id) ? $result->drc_internal_id : $result->participant_id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function getEvaluation($participantId, $evaluationId)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$participantId}/PhysicalEvaluation/{$evaluationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
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
            $response = $this->getClient()->request('POST', "Participant/{$participantId}/PhysicalEvaluation", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /*
     * Evaluation PUT method is not yet supported
     */
    public function updateEvaluation($participantId, $evaluationId, $evaluation)
    {
        try {
            $response = $this->getClient()->request('PUT', "Participant/{$participantId}/PhysicalEvaluation/{$evaluationId}", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function getOrder($participantId, $orderId)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$participantId}/BiobankOrder/{$orderId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    public function createOrder($participantId, $order)
    {
        try {
            $response = $this->getClient()->request('POST', "Participant/{$participantId}/BiobankOrder", [
                'json' => $order
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }

    /*
     * Order PUT method is not yet supported
     */
    public function updateOrder($participantId, $orderId, $order)
    {
        try {
            $response = $this->getClient()->request('PUT', "Participant/{$participantId}/BiobankOrder/{$orderId}", [
                'json' => $order
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            return false;
        }
        return false;
    }
}
