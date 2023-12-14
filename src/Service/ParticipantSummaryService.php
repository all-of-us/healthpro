<?php

namespace App\Service;

use App\Drc\Exception\FailedRequestException;
use App\Drc\Exception\InvalidDobException;
use App\Drc\Exception\InvalidResponseException;
use App\Entity\Site;
use App\Helper\Participant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ParticipantSummaryService
{
    public const CACHE_TIME = 300;
    public const DS_CLEAN_UP_LIMIT = 500;

    protected $api;
    protected $nextToken;
    protected $total;
    protected $params;
    protected $em;
    protected $disableTestAccess;

    public function __construct(RdrApiService $api, ParameterBagInterface $params, EntityManagerInterface $em)
    {
        $this->api = $api;
        $this->params = $params;
        $this->em = $em;
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
        $cache = new \App\Cache\DatastoreAdapter($dsCleanUpLimit);
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
                $disableTestAccess = $this->params->has('disable_test_access') ? $this->params->get('disable_test_access') : '';
                $cohortOneLaunchTime = $this->params->has('cohort_one_launch_time') ? $this->params->get('cohort_one_launch_time') : '';
                if ($participant) {
                    $participant->options = [
                        'disableTestAccess' => $disableTestAccess,
                        'siteType' => $this->getSiteType($participant->awardee),
                        'cohortOneLaunchTime' => $cohortOneLaunchTime
                    ];
                }
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
        $query = $this->paramsToQuery($params);
        try {
            $response = $this->api->get('rdr/v1/ParticipantSummary', [
                'query' => $query
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
                $results[] = new Participant($participant->resource);
            }
        }

        return $results;
    }

    public function getSiteType($awardeeId)
    {
        $site = $this->em->getRepository(Site::class)->findOneBy(['awardeeId' => $awardeeId]);
        if (!empty($site)) {
            return strtolower($site->getType()) === 'dv' ? 'dv' : 'hpo';
        }
        return null;
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
                throw new InvalidDobException();
            }
            if (strpos($params['dob'], $date->format('Y')) === false) {
                throw new InvalidDobException('Please enter a four digit year');
            } elseif ($date > new \DateTime('today')) {
                throw new InvalidDobException('Date of birth cannot be a future date');
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
}
