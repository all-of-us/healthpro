<?php

namespace App\Service\Nph;

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
                $query = '
                  query {
                    participant (nphId: 10001000000001) {
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
            return $participant;
        }
        return false;
    }
}
