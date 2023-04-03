<?php

namespace App\Service;

use App\Cache\DatastoreAdapter;
use App\HttpClient;
use Google\Client as GoogleClient;
use Google\Service\Oauth2 as GoogleServiceOauth2;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class RdrApiService
{
    protected $googleClient;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $config = [];
    protected $cache;
    protected $logger;
    protected $lastError;
    protected $lastErrorCode;

    public function __construct(EnvironmentService $environment, KernelInterface $appKernel, GoogleClient $googleClient, ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->googleClient = $googleClient;
        $basePath = $appKernel->getProjectDir();
        // Note that when installed in ./symfony, the development credentials are a level down
        if ($environment->isLocal() && file_exists($basePath . '/dev_config/rdr_key.json')) {
            $this->config['key_file'] = $basePath . '/dev_config/rdr_key.json';
        }
        if ($params->has('rdr_auth_json')) {
            $this->config['rdr_auth_json'] = $params->get('rdr_auth_json');
        }
        // Load endpoint from configuration
        if ($params->has('rdr_endpoint')) {
            $this->endpoint = $params->get('rdr_endpoint');
        }
        // Set up OAuth Cache
        if (!$params->has('rdr_auth_cache_disabled')) {
            $this->logger = $logger;
            $this->cache = new DatastoreAdapter($params->get('ds_clean_up_limit'));
            $this->cache->setLogger($this->logger);
        }
    }

    public function get($path, $params = [])
    {
        return $this->getClient($path)->request('GET', $this->endpoint . $path, $params);
    }

    public function post($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->getClient($path)->request('POST', $this->endpoint . $path, $params);
    }

    public function put($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->getClient($path)->request('PUT', $this->endpoint . $path, $params);
    }

    public function patch($path, $body, $params = [])
    {
        $params['json'] = $body;
        return $this->getClient($path)->request('PATCH', $this->endpoint . $path, $params);
    }

    public function GQLPost(string $path, string $query): ResponseInterface
    {
        $params = [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => $query
        ];
        return $this->getClient($path)->request('POST', $this->endpoint . $path, $params);
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

    // Private Methods

    private function getClient($resourceEndpoint = null)
    {
        if (!empty($this->config['rdr_auth_json'])) {
            $this->googleClient->setAuthConfig(json_decode($this->config['rdr_auth_json'], true));
        } else {
            if (!empty($this->config['key_file'])) {
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->config['key_file']);
            }
            $this->googleClient->useApplicationDefaultCredentials();
        }

        $this->googleClient->addScope(GoogleServiceOauth2::USERINFO_EMAIL);

        if ($resourceEndpoint) {
            $endpoint = $this->endpoint . $resourceEndpoint;
        } else {
            $endpoint = $this->endpoint;
        }

        if ($this->cache) {
            $this->googleClient->setCache($this->cache);
        }

        return $this->googleClient->authorize(new HttpClient([
            'base_uri' => $endpoint,
            'timeout' => 50
        ]));
    }
}
