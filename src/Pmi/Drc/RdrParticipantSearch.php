<?php
namespace Pmi\Drc;

class RdrParticipantSearch
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

        return (object)[
            'id' => $participant->drc_internal_id,
            'firstName' => $participant->first_name,
            'lastName' => $participant->last_name,
            'dob' => new \DateTime($participant->date_of_birth),
            'gender' => 'F',
            'zip' => isset($participant->zip_code) ? $participant->zip_code : null,
            'consentComplete' => isset($participant->enrollment_status) ? $participant->enrollment_status : null
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
                $query['date_of_birth'] = $date->format('Y-m-d\T00:00:00');
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
}
