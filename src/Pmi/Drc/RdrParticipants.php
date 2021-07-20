<?php
namespace Pmi\Drc;

use Pmi\Entities\Participant;
use Pmi\Evaluation\Evaluation;
use Pmi\Order\Order;
use Symfony\Contracts\Cache\ItemInterface;

class RdrParticipants
{
    protected $rdrHelper;
    protected $client;
    protected $cacheEnabled = true;
    protected $cache;
    protected $cacheTime;
    protected static $resourceEndpoint = 'rdr/v1/';
    protected $nextToken;
    protected $total;

    private $disableTestAccess;
    private $cohortOneLaunchTime;

    // Expected RDR response status

    const EVALUATION_CANCEL_STATUS = 'entered-in-error';
    const EVALUATION_RESTORE_STATUS = 'final';
    const ORDER_CANCEL_STATUS = 'CANCELLED';
    const ORDER_RESTORE_STATUS = 'UNSET';
    const ORDER_EDIT_STATUS = 'AMENDED';

    public function __construct(RdrHelper $rdrHelper)
    {
        $this->rdrHelper = $rdrHelper;
        $this->cacheEnabled = $rdrHelper->isCacheEnabled();
        $this->cache = $rdrHelper->getCache();
        $this->cacheTime = $rdrHelper->getCacheTime();
        $this->disableTestAccess = $rdrHelper->getDisableTestAccess();
        $this->cohortOneLaunchTime = $rdrHelper->getCohortOneLaunchTime();
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
        return new Participant($participant);
    }

    protected function paramsToQuery($params)
    {
        $query = [];
        if (isset($params['lastName'])) {
            $query['lastName'] = $params['lastName'];
        }
        if (isset($params['firstName'])) {
            $query['firstName'] = $params['firstName'];
        }
        if (isset($params['dob'])) {
            try {
                $date = new \DateTime($params['dob']);
                $query['dateOfBirth'] = $date->format('Y-m-d');
            } catch (\Exception $e) {
                throw new Exception\InvalidDobException();
            }
            if (strpos($params['dob'], $date->format('Y')) === false) {
                throw new Exception\InvalidDobException('Please enter a four digit year');
            } elseif ($date > new \DateTime('today')) {
                throw new Exception\InvalidDobException('Date of birth cannot be a future date');
            }
        }
        if (isset($params['phone'])) {
            $query['phoneNumber'] = $params['phone'];
        }
        if (isset($params['loginPhone'])) {
            $query['loginPhoneNumber'] = $params['loginPhone'];
        }
        if (isset($params['email'])) {
            $query['email'] = strtolower($params['email']);
        }
        if (isset($params['biobankId'])) {
            $query['biobankId'] = $params['biobankId'];
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
            $this->rdrHelper->logException($e);
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
            if (isset($participant->resource) && is_object($participant->resource)) {
                $participant->resource->disableTestAccess = $this->disableTestAccess;
                if ($result = $this->participantToResult($participant->resource)) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    /**
     * @param string|array $params Particpant Summary API parameters (query string or array)
     * @param bool $next Enable paging
     **/
    public function listParticipantSummaries($params, $next = false)
    {
        if ($next) {
            //Pass token if exists
            if ($this->nextToken) {
                if (is_array($params)) {
                    $params['_token'] = $this->nextToken;
                } else {
                    $params .= '&_token=' . $this->nextToken;
                }
            }
        } else {
            // Request count
            if (is_array($params)) {
                $params['_includeTotal'] = 'true';
            } else {
                $params .= '&_includeTotal=true';
            }
        }
        $this->nextToken = $this->total = null;
        try {
            $response = $this->getClient()->request('GET', 'ParticipantSummary', [
                'query' => $params
            ]);
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            throw new Exception\FailedRequestException();
        }
        $contents = $response->getBody()->getContents();
        $responseObject = json_decode($contents);
        if (!is_object($responseObject)) {
            throw new Exception\InvalidResponseException();
        }
        if (!isset($responseObject->entry) || !is_array($responseObject->entry)) {
            return [];
        }
        if (isset($responseObject->link) && is_array($responseObject->link)) {
            foreach ($responseObject->link as $link) {
                if ($link->relation === 'next') {
                    $queryString = parse_url($link->url, PHP_URL_QUERY);
                    parse_str($queryString, $nextParameters);
                    if (isset($nextParameters['_token'])) {
                        $this->nextToken = $nextParameters['_token'];
                    }
                    break;
                }
            }
        }
        if (isset($responseObject->total)) {
            $this->total = intval($responseObject->total);
        }
        return $responseObject->entry;
    }

    public function getNextToken()
    {
        return $this->nextToken;
    }

    public function getTotal()
    {
        return $this->total;
    }

    private function getParticipantSummary($id)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$id}/Summary");
            $participant = json_decode($response->getBody()->getContents());
            $participant->options = [
                'disableTestAccess' => $this->disableTestAccess,
                'siteType' => isset($participant->awardee) ? $this->rdrHelper->getSiteType($participant->awardee) : null,
                'cohortOneLaunchTime' => $this->cohortOneLaunchTime
            ];
            return $participant;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return false;
        }
    }

    public function getByIdUsingCacheContract($id, $refresh = null)
    {
        if ($this->cacheEnabled) {
            $cacheKey = 'rdr_participant_' . $id;
            $beta = $refresh ? INF : null; // set early expiration to infinite to refresh
            $participant = $this->cache->get($cacheKey, function (ItemInterface $item) use ($id) {
                $item->expiresAfter($this->cacheTime);
                $participant = $this->getParticipantSummary($id);
                $participant->cacheTime = new \DateTime();
                return $participant;
            }, $beta);
            if (!$participant) {
                $this->cache->delete($cacheKey);
            }
        } else {
            $participant = $this->getParticipantSummary($id);
        }
        if ($participant) {
            return $this->participantToResult($participant);
        } else {
            return false;
        }
    }

    public function getById($id, $refresh = null)
    {
        if (!is_string($id) || !preg_match('/^\w+$/', $id)) {
            return false;
        }

        $participant = false;
        $cacheKey = 'rdr_participant_' . $id;

        if ($this->cacheEnabled && !$refresh) {
            try {
                $cacheItem = $this->cache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $participant = $cacheItem->get();
                }
            } catch (\Exception $e) {
                $this->rdrHelper->getLogger()->error($e);
            }
        }
        if (!$participant) {
            $participant = $this->getParticipantSummary($id);
            if ($participant && $this->cacheEnabled) {
                $participant->cacheTime = new \DateTime();
                $cacheItem = $this->cache->getItem($cacheKey);
                $cacheItem->expiresAfter($this->cacheTime);
                $cacheItem->set($participant);
                $this->cache->save($cacheItem);
            }
        }
        if ($participant) {
            return $this->participantToResult($participant);
        } else {
            return false;
        }
    }

    public function getEvaluation($participantId, $evaluationId)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$participantId}/PhysicalMeasurements/{$evaluationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function createEvaluation($participantId, $evaluation)
    {
        try {
            $response = $this->getClient()->request('POST', "Participant/{$participantId}/PhysicalMeasurements", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->id)) {
                return $result->id;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function cancelRestoreEvaluation($type, $participantId, $evaluationId, $evaluation)
    {
        try {
            $response = $this->getClient()->request('PATCH', "Participant/{$participantId}/PhysicalMeasurements/{$evaluationId}", [
                'json' => $evaluation
            ]);
            $result = json_decode($response->getBody()->getContents());

            // Check RDR response
            $rdrStatus = $type === Evaluation::EVALUATION_CANCEL ? self::EVALUATION_CANCEL_STATUS : self::EVALUATION_RESTORE_STATUS;
            if (is_object($result) && is_array($result->entry)) {
                foreach ($result->entry as $entries) {
                    if (strtolower($entries->resource->resourceType) === 'composition') {
                        return $entries->resource->status === $rdrStatus ? true : false;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function getOrdersByParticipant($participantId)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$participantId}/BiobankOrder");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && is_array($result->data)) {
                return $result->data;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return [];
        }
        return [];
    }

    public function getOrders($query = [])
    {
        try {
            $response = $this->getClient()->request('GET', 'BiobankOrder', [
                'query' => $query
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && is_array($result->data)) {
                return $result->data;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return [];
        }
        return [];
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
            $this->rdrHelper->logException($e);
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
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function cancelRestoreOrder($type, $participantId, $orderId, $order)
    {
        try {
            $result = $this->getOrder($participantId, $orderId);
            $response = $this->getClient()->request('PATCH', "Participant/{$participantId}/BiobankOrder/{$orderId}", [
                'json' => $order,
                'headers' => ['If-Match' => $result->meta->versionId]
            ]);
            $result = json_decode($response->getBody()->getContents());
            $rdrStatus = $type === Order::ORDER_CANCEL ? self::ORDER_CANCEL_STATUS : self::ORDER_RESTORE_STATUS;
            if (is_object($result) && isset($result->status) && $result->status === $rdrStatus) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function editOrder($participantId, $orderId, $order)
    {
        try {
            $result = $this->getOrder($participantId, $orderId);
            $response = $this->getClient()->request('PUT', "Participant/{$participantId}/BiobankOrder/{$orderId}", [
                'json' => $order,
                'headers' => ['If-Match' => $result->meta->versionId]
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->status) && $result->status === self::ORDER_EDIT_STATUS) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function getLastError()
    {
        return $this->rdrHelper->getLastError();
    }

    public function getLastErrorCode()
    {
        return $this->rdrHelper->getLastErrorCode();
    }

    public function getCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function createPatientStatus($participantId, $organizationId, $data)
    {
        // RDR supports PUT for both create and update requests
        try {
            $response = $this->getClient()->request('PUT', "PatientStatus/{$participantId}/Organization/$organizationId", [
                'json' => $data
            ]);
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result) && isset($result->authored)) {
                return true;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function getPatientStatus($participantId, $organizationId)
    {
        try {
            $response = $this->getClient()->request('GET', "PatientStatus/{$participantId}/Organization/{$organizationId}");
            $result = json_decode($response->getBody()->getContents());
            if (is_object($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function getPatientStatusHistory($participantId, $organizationId)
    {
        try {
            $response = $this->getClient()->request('GET', "PatientStatus/{$participantId}/Organization/{$organizationId}/History");
            $result = json_decode($response->getBody()->getContents());
            if (is_array($result)) {
                return $result;
            }
        } catch (\Exception $e) {
            $this->rdrHelper->logException($e);
            return false;
        }
        return false;
    }

    public function getByIdRaw($id)
    {
        try {
            $response = $this->getClient()->request('GET', "Participant/{$id}/Summary");
            $participant = json_decode($response->getBody()->getContents(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            return false;
        }
        return $participant;
    }
}
