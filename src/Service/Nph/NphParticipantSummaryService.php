<?php

namespace App\Service\Nph;

use App\Drc\Exception\FailedRequestException;
use App\Drc\Exception\InvalidResponseException;
use App\Helper\Participant;
use App\Service\RdrApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NphParticipantSummaryService
{
    public const CACHE_TIME = 300;
    public const DS_CLEAN_UP_LIMIT = 500;

    protected $api;
    protected $nextToken;
    protected $total;
    protected $params;
    protected $em;

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
        $cacheKey = 'nph_rdr_participant_' . $participantId;
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
                $query = $this->getParticipantByIdQuery($participantId);
                $response = $this->api->GQLPost('rdr/v1/nph_participant', $query);
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

    /**
     * @throws FailedRequestException
     * @throws InvalidResponseException
     */
    public function search($params): ?array
    {
        $query = $this->getSearchQuery($params);
        try {
            $response = $this->api->GQLPost('rdr/v1/nph_participant', $query);
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
                if ($result = new Participant($participant->resource)) {
                    $results[] = $result;
                }
            }
        }

        return $results;
    }

    private function getParticipantByIdQuery(string $participantId): string
    {
        //TODO
        return " 
            query {
                participant (nphId: {$participantId}) {
                    totalCount
                    resultCount
                    edges {
                        node {
                            firstName
                            lastName
                        }
                    }
                }
              }
        ";
    }

    private function getSearchQuery(array $params): string
    {
        //TODO
        return ' 
            query {
                participant (firstName: test) {
                    totalCount
                    resultCount
                    edges {
                        node {
                            firstName
                            lastName
                        }
                    }
                }
              }
        ';
    }

}
