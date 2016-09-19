<?php
namespace Pmi\Drc;

class ParticipantSearch
{
    protected static $endpoint = 'https://pmi-rdr-api-test.appspot.com/_ah/api/participant/v1/';

    protected function getClient()
    {
        $googleClient = new \Google_Client();
        $googleClient->useApplicationDefaultCredentials();
        $googleClient->addScope(\Google_Service_Oauth2::USERINFO_EMAIL);
        return $googleClient->authorize(new \GuzzleHttp\Client([
            'base_uri' => self::$endpoint
        ]));
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
            'zip' => $participant->zip_code,
            'consentComplete' => $participant->enrollment_status
        ];
    }

    public function search($params)
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

            }
        }
        $client = $this->getClient();
        $response = $client->request('GET', 'participants', [
            'query' => $query
        ]);
        $responseObject = json_decode($response->getBody()->getContents());
        $results = [];
        if (!is_object($responseObject) || !isset($responseObject->items) || !is_array($responseObject->items)) {
            return $results;
        }
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
        $client = $this->getClient();
        $response = $client->request('GET', "participants/{$id}");
        $participant = json_decode($response->getBody()->getContents());
        return $this->participantToResult($participant);
    }
}
