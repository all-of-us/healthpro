<?php

namespace App\Service\Nph;

use App\Drc\Exception\FailedRequestException;
use App\Drc\Exception\InvalidDobException;
use App\Drc\Exception\InvalidResponseException;
use App\Helper\NphParticipant;
use App\Service\RdrApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class NphParticipantSummaryService
{
    public const CACHE_TIME = 300;
    public const DS_CLEAN_UP_LIMIT = 500;

    protected RdrApiService $api;
    protected ParameterBagInterface $params;
    protected EntityManagerInterface $em;

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
        $dsCleanUpLimit = $this->params->has('ds_clean_up_limit') ? $this->params->get('ds_clean_up_limit') : self::DS_CLEAN_UP_LIMIT;
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
                $result = json_decode($response->getBody()->getContents());
                $edges = $result->participant->edges;
                $participant = !empty($edges) ? $edges[0]->node : null;
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
            return new NphParticipant($participant);
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
            error_log($e->getMessage());
            throw new FailedRequestException();
        }

        $result = json_decode($response->getBody()->getContents());
        if (!is_object($result)) {
            throw new InvalidResponseException();
        }

        $edges = $result->participant->edges;

        if (empty($edges)) {
            return [];
        }
        $results = [];
        foreach ($edges as $edge) {
            $results[] = new NphParticipant($edge->node);
        }

        return $results;
    }

    public function getAllParticipantDetailsById($participantId): ?array
    {
        try {
            $query = $this->getAllParticipantsByIdQuery($participantId);
            $response = $this->api->GQLPost('rdr/v1/nph_participant', $query);
            $result = json_decode($response->getBody()->getContents(), true);
            $edges = $result['participant']['edges'];
            return !empty($edges) ? $edges[0]['node'] : null;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    private function getParticipantByIdQuery(string $participantId): string
    {
        return " 
            query {
                participant (nphId: \"{$participantId}\") {
                    totalCount
                    resultCount
                    edges {
                        node {
                            firstName
                            lastName
                            participantNphId
                            DOB
                            biobankId
                            nphPairedSite
                        }
                    }
                }
              }
        ";
    }


    /**
     * @throws InvalidDobException
     */
    private function getSearchQuery(array $params): string
    {
        $searchParams = [];
        foreach ($params as $field => $value) {
            if (!empty($value)) {
                if ($field === 'dob') {
                    try {
                        $date = new \DateTime($params['dob']);
                        $field = 'dateOfBirth';
                        $value = $date->format('Y-m-d');
                    } catch (\Exception $e) {
                        throw new InvalidDobException();
                    }
                    if (strpos($params['dob'], $date->format('Y')) === false) {
                        throw new InvalidDobException('Please enter a four digit year');
                    } elseif ($date > new \DateTime('today')) {
                        throw new InvalidDobException('Date of birth cannot be a future date');
                    }
                }
                if ($field === 'email') {
                    $value = strtolower($value);
                }
                if ($field === 'phone') {
                    $field = 'phoneNumber';
                }
                $searchParams[] = "{$field}: \"{$value}\"";
            }
        }
        $searchParams = implode(',', $searchParams);
        return " 
            query {
                participant ({$searchParams}) {
                    totalCount
                    resultCount
                    edges {
                        node {
                            firstName
                            lastName
                            participantNphId
                            DOB
                            biobankId
                            nphPairedSite
                        }
                    }
                }
              }
        ";
    }

    private function getAllParticipantsByIdQuery(string $participantId): string
    {
        return " 
            query {
                participant (nphId: \"{$participantId}\") {
                    totalCount
                    resultCount
                    edges {
                        node {
                            aouAianStatus
                            aouBasicsStatus {
                                time,
                                value
                            }
                            aouDeactivationStatus {
                                time,
                                value
                            }
                            aouDeceasedStatus {
                                time,
                                value
                            }
                            aouEnrollmentStatus {
                                time,
                                value
                            }
                            aouLifestyleStatus {
                                time,
                                value
                            }
                            aouOverallHealthStatus {
                                time
                                value
                            }
                            aouSDOHStatus {
                                time
                                value
                            }
                            aouWithdrawalStatus {
                                time
                                value
                            }
                            biobankId
                            DOB
                            email
                            firstName
                            lastName
                            middleName
                            nphDeactivationStatus {
                                time
                                value
                            }
                            nphEnrollmentStatus {
                                time
                                value
                            }
                            nphPairedAwardee
                            nphPairedOrg
                            nphPairedSite
                            nphWithdrawalStatus {
                                time
                                value
                            }
                            participantNphId
                            phoneNumber
                            siteId
                            zipCode
                        }
                    }
                }
            }
        ";
    }
}
