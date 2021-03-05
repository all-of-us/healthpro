<?php

namespace App\Service;

use App\Service\RdrApiService;
use App\Helper\Participant;
use Pmi\Drc\Exception\FailedRequestException;
use Pmi\Drc\Exception\InvalidResponseException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ParticipantSummaryService
{
    const CACHE_TIME = 300;
    const DS_CLEAN_UP_LIMIT = 500;

    protected $api;
    protected $nextToken;
    protected $total;
    protected $params;
    protected $disableTestAccess;

    public function __construct(RdrApiService $api, ParameterBagInterface $params)
    {
        $this->api = $api;
        $this->params = $params;
    }

    public function getParticipantById($participantId, $refresh = null)
    {
        if (!is_string($participantId) || !preg_match('/^\w+$/', $participantId)) {
            return false;
        }
        $participant = false;
        $cacheKey = 'rdr_participant_' . $participantId;
        $cacheEnabled = $this->params->has('rdr_disable_cache') ? !$this->params->get('rdr_disable_cache') : true;
        $cacheTime = $this->params->has('cache_time') ? intval($this->params->get('cache_time')) : self::CACHE_TIME;
        $dsCleanUpLimit = $this->params->has('ds_clean_up_limit') ? $this->params->has('ds_clean_up_limit') : self::DS_CLEAN_UP_LIMIT;
        $cache = new \Pmi\Cache\DatastoreAdapter($dsCleanUpLimit);
        if ($cacheEnabled && !$refresh) {
            try {
                $cacheItem = $cache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $participant = $cacheItem->get();
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }
        if (!$participant) {
            try {
                $response = $this->api->get(sprintf('rdr/v1/Participant/%s/Summary', $participantId));
                $participant = json_decode($response->getBody()->getContents());
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return false;
            }
            if ($participant && $cacheEnabled) {
                $participant->cacheTime = new \DateTime();
                $cacheItem = $cache->getItem($cacheKey);
                $cacheItem->expiresAfter($cacheTime);
                $cacheItem->set($participant);
                $cache->save($cacheItem);
            }
        }
        if ($participant) {
            return new Participant($participant);
        }
        return false;
    }

    public function listParticipantSummaries($params)
    {
        try {
            $response = $this->api->get('rdr/v1/ParticipantSummary', [
                'query' => $params
            ]);
        } catch (\Exception $e) {
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

    /**
     * @param string|array $params Particpant Summary API parameters (query string or array)
     * @param bool $next Enable paging
     **/
    public function listWorkQueueParticipantSummaries($params, $next = false)
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
            $response = $this->api->get('rdr/v1/ParticipantSummary', [
                'query' => $params
            ]);
        } catch (\Exception $e) {
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

    public function getTotal()
    {
        return $this->total;
    }

    public function getNextToken()
    {
        return $this->nextToken;
    }

    public function search($params)
    {
        try {
            $response = $this->api->get('rdr/v1/ParticipantSummary', [
                'query' => $params
            ]);
        } catch (\Exception $e) {
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
        $results = [];
        foreach ($responseObject->entry as $participant) {
            if (isset($participant->resource) && is_object($participant->resource)) {
                $participant->resource->disableTestAccess = $this->disableTestAccess;
                if ($result = new Participant($participant->resource)) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }
}
