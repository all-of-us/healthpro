<?php

namespace App\Service\Ppsc;

use App\Helper\PpscParticipant;
use App\HttpClient;
use App\Service\EnvironmentService;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PpscApiService
{
    public const CACHE_TIME = 300;
    public const DS_CLEAN_UP_LIMIT = 500;

    public HttpClient $client;
    protected LoggerInterface $logger;
    protected $lastError;
    protected $lastErrorCode;
    private ParameterBagInterface $params;
    private SessionInterface $session;
    private EnvironmentService $env;
    private string|null $tokenUrl;
    private string|null $clientId;
    private string|null $clientSecret;
    private string|null $grantType;
    private string|null $scope;
    private string|null $accessToken = null;
    private string|null $endpoint;

    public function __construct(ParameterBagInterface $params, SessionInterface $session, EnvironmentService $env, LoggerInterface $logger)
    {
        $this->params = $params;
        $this->session = $session;
        $this->env = $env;
        $this->logger = $logger;
        $this->client = new HttpClient(['cookies' => true]);
        $this->endpoint = $this->getParams('ppsc_endpoint');
        $this->tokenUrl = $this->getParams('ppsc_token_url');
        $this->clientId = $this->getParams('ppsc_client_id');
        $this->clientSecret = $this->getParams('ppsc_client_secret');
        $this->grantType = $this->getParams('ppsc_grant_type');
        $this->scope = $this->getParams('ppsc_scope');
    }

    public function getRequestDetailsById($requestId): \stdClass|null
    {
        if (empty($requestId)) {
            return null;
        }
        $token = $this->getAccessToken();
        $retry = true;
        while (true) {
            try {
                $response = $this->client->request('GET', $this->endpoint . 'requests/' . $requestId, [
                    'headers' => ['Authorization' => 'Bearer ' . $token]
                ]);
                $requestDetailsData = json_decode($response->getBody()->getContents());
                return $requestDetailsData ?? null;
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                    $token = $this->getAccessToken(true);
                    $retry = false;
                } else {
                    $this->logException($e);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logException($e);
                return null;
            }
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
            $token = $this->getAccessToken();
            $retry = true;
            while (true) {
                try {
                    $participant = $this->fetchParticipantById($participantId, $token);
                    break;
                } catch (ClientException $e) {
                    if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                        $token = $this->getAccessToken(true);
                        $retry = false;
                    } else {
                        $this->logException($e);
                        return null;
                    }
                } catch (\Exception $e) {
                    $this->logException($e);
                    return null;
                }
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

    public function getParticipantByBiobankId(string $biobankId): PpscParticipant|null
    {
        $token = $this->getAccessToken();
        $retry = true;
        while (true) {
            try {
                $participant = $this->fetchParticipantByBiobankId($biobankId, $token);
                break;
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                    $token = $this->getAccessToken(true);
                    $retry = false;
                } else {
                    $this->logException($e);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logException($e);
                return null;
            }
        }
        if (!empty($participant)) {
            return new PpscParticipant($participant);
        }
        return null;
    }

    public function getRawParticipantById(string $participantId): array|null
    {
        $token = $this->getAccessToken();
        $retry = true;
        while (true) {
            try {
                return $this->fetchParticipantById($participantId, $token, true);
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                    $token = $this->getAccessToken(true);
                    $retry = false;
                } else {
                    $this->logException($e);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logException($e);
                return null;
            }
        }
    }

    public function getAccessToken(bool $refresh = false): string|null
    {
        $dsCleanUpLimit = $this->params->has('ds_clean_up_limit') ? $this->params->get('ds_clean_up_limit') : self::DS_CLEAN_UP_LIMIT;
        $cacheKey = 'ppsc_access_token';
        $cache = new \App\Cache\DatastoreAdapter($dsCleanUpLimit);

        // If refresh is not forced, try to get the token from the cache.
        if (!$refresh) {
            try {
                $cacheItem = $cache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $this->accessToken = $cacheItem->get();
                    return $this->accessToken;
                }
            } catch (\Exception $e) {
                error_log($e->getMessage());
            }
        }

        // Request a new token if none is cached or if we are refreshing.
        try {
            $response = $this->client->request('POST', $this->tokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . base64_encode("{$this->clientId}:{$this->clientSecret}")
                ],
                'form_params' => [
                    'grant_type' => $this->grantType,
                    'scope' => $this->scope
                ]
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['access_token'])) {
                $this->accessToken = $data['access_token'];

                // Cache the token using the expires_in value (in seconds) as TTL.
                try {
                    $cacheItem = $cache->getItem($cacheKey);
                    // Subtract a few seconds to account for any latency.
                    $ttl = isset($data['expires_in']) ? intval($data['expires_in']) - 10 : 3600;
                    $cacheItem->expiresAfter($ttl);
                    $cacheItem->set($this->accessToken);
                    $cache->save($cacheItem);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
                return $this->accessToken;
            }
            return null;
        } catch (\Exception $e) {
            $this->logException($e);
            return null;
        }
    }

    public function get(string $path, array $params = []): ?ResponseInterface
    {
        $token = $this->getAccessToken();
        $retry = true;

        while (true) {
            try {
                $params['headers'] = ['Authorization' => 'Bearer ' . $token];
                return $this->client->request('GET', $this->endpoint . $path, $params);
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 401 && $retry) {
                    $token = $this->getAccessToken(true);
                    $retry = false;
                } else {
                    $this->logException($e);
                    return null;
                }
            } catch (\Exception $e) {
                $this->logException($e);
                return null;
            }
        }
    }

    public function post(string $path, \stdClass $body, array $params = []): ResponseInterface
    {
        $token = $this->getAccessToken(true);
        $params['headers'] = ['Authorization' => 'Bearer ' . $token];
        $params['json'] = $body;
        return $this->client->request('POST', $this->endpoint . $path, $params);
    }

    public function put(string $path, \stdClass $body, array $params = []): ResponseInterface
    {
        $token = $this->getAccessToken(true);
        $params['headers'] = ['Authorization' => 'Bearer ' . $token];
        $params['json'] = $body;
        return $this->client->request('PUT', $this->endpoint . $path, $params);
    }

    public function patch(string $path, \stdClass $body, array $params = []): ResponseInterface
    {
        $token = $this->getAccessToken(true);
        $params['headers'] = ['Authorization' => 'Bearer ' . $token];
        $params['json'] = $body;
        return $this->client->request('PATCH', $this->endpoint . $path, $params);
    }

    public function logException(\Exception $e)
    {
        $this->lastError = $e->getMessage();
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $this->logger->critical($e->getMessage());
            $response = $e->getResponse();
            $responseCode = $response->getStatusCode();
            $contents = $response->getBody()->getContents();
            $this->logger->info("Response code: {$responseCode}");
            $this->logger->info("Response body: {$contents}");
            $this->lastError = $contents;
            $this->lastErrorCode = $responseCode;
        } else {
            // No response - request probably timed out
            $this->logger->error($e->getMessage());
        }
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getLastErrorCode()
    {
        return $this->lastErrorCode;
    }

    private function fetchParticipantById(string $participantId, string $token, bool $associative = false)
    {
        $response = $this->client->request('GET', $this->endpoint . 'participants/' . $participantId, [
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);

        return json_decode($response->getBody()->getContents(), $associative);
    }

    private function fetchParticipantByBiobankId(string $biobankId, string $token)
    {
        $response = $this->client->request('GET', $this->endpoint . 'participants?bioBankId=' . $biobankId, [
            'headers' => ['Authorization' => 'Bearer ' . $token]
        ]);

        return json_decode($response->getBody()->getContents());
    }

    private function getParams($field): string|null
    {
        $ppscEnv = $this->env->getPpscEnv($this->session->get('ppscEnv'));
        return $this->params->has($ppscEnv . '_' . $field) ? $this->params->get($ppscEnv . '_' . $field) : null;
    }
}
