<?php

namespace App\Service\Ppsc;

use App\Helper\PpscParticipant;
use App\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class PpscApiService
{
    public const CACHE_TIME = 300;
    public const DS_CLEAN_UP_LIMIT = 500;

    public HttpClient $client;
    private ParameterBagInterface $params;
    private string|null $tokenUrl;
    private string|null $clientId;
    private string|null $clientSecret;
    private string|null $grantType;
    private string|null $accessToken = null;
    private string|null $endpoint;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        $this->client = new HttpClient(['cookies' => true]);
        $this->endpoint = $this->getParams('ppsc_endpoint');
        $this->tokenUrl = $this->getParams('ppsc_token_url');
        $this->clientId = $this->getParams('ppsc_client_id');
        $this->clientSecret = $this->getParams('ppsc_client_secret');
        $this->grantType = $this->getParams('ppsc_grant_type');
    }

    public function getRequestDetailsById($requestId): \stdClass|null
    {
        try {
            $token = $this->getAccessToken();
            $response = $this->client->request('GET', $this->endpoint . 'getRequestDetails', [
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'query' => ['requestId' => $requestId]
            ]);
            $requestDetailsData = json_decode($response->getBody()->getContents());
            return $requestDetailsData ? $requestDetailsData[0] : null;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function getParticipantById(string $participantId, ?string $refresh = null): PpscParticipant|null
    {
        $participant = false;
        $cacheKey = 'ppsc_participant_' . $participantId;
        $cacheEnabled = $this->params->has('ppsc_disable_cache') ? !$this->params->get('ppsc_disable_cache') : true;
        $cacheTime = $this->params->has('ppsc_cache_time') ? intval($this->params->get('ppsc_cache_time')) : self::CACHE_TIME;
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
                $token = $this->getAccessToken();
                $response = $this->client->request('GET', $this->endpoint . 'getParticipantDetails', [
                    'headers' => ['Authorization' => 'Bearer ' . $token],
                    'query' => ['participantId' => $participantId]
                ]);
                $participant = json_decode($response->getBody()->getContents());
            } catch (\Exception $e) {
                error_log($e->getMessage());
                return null;
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
            return new PpscParticipant($participant);
        }
        return null;
    }

    public function getAccessToken(): string|null
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        try {
            $response = $this->client->request('POST', $this->tokenUrl, [
                'form_params' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'grant_type' => $this->grantType
                ]
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];
                return $this->accessToken;
            }
            return null;
        } catch (\Exception $e) {
            error_log($e->getMessage());
            return null;
        }
    }

    public function post(string $path, \stdClass $body, array $params = []): ResponseInterface
    {
        $token = $this->getAccessToken();
        $params['headers'] = ['Authorization' => 'Bearer ' . $token];
        $params['json'] = $body;
        return $this->client->request('POST', $this->endpoint . $path, $params);
    }

    private function getParams($field): string|null
    {
        return $this->params->has($field) ? $this->params->get($field) : null;
    }
}
