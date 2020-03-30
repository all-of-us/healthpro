<?php
namespace Pmi\Drc;

use Pmi\HttpClient;

class RdrHelper
{
    protected $client;
    protected $options = [];
    protected $config = [];
    protected $logger;
    protected $cache;
    protected $em;
    protected $lastError;
    protected $lastErrorCode;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $cacheEnabled = true;
    protected $cacheTime = 300;
    protected $disableTestAccess = false;
    protected $genomicsStartTime;

    public function __construct(array $options)
    {
        if (!empty($options)) {
            $this->options = $options;
            $this->config = $options['config'];
            $this->logger = $options['logger'];
            $this->cache = $options['cache'];
            $this->em = $options['em'];
            $this->setConfigs();
        }
    }

    public function getClient($resourceEndpoint = null)
    {
        $googleClient = new \Google_Client();

        if (!empty($this->config['rdr_auth_json'])) {
            $googleClient->setAuthConfig(json_decode($this->config['rdr_auth_json'], true));
        } else {
            if (!empty($this->options['key_file'])) {
                putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->options['key_file']);
            }
            $googleClient->useApplicationDefaultCredentials();
        }

        $googleClient->addScope(\Google_Service_Oauth2::USERINFO_EMAIL);

        if ($resourceEndpoint) {
            $endpoint = $this->endpoint . $resourceEndpoint;
        } else {
            $endpoint = $this->endpoint;
        }

        $googleClient->setCache($this->cache);

        return $googleClient->authorize(new HttpClient([
            'base_uri' => $endpoint,
            'timeout' => 50
        ]));
    }

    public function isCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function getCacheTime()
    {
        return $this->cacheTime;
    }

    public function getCache()
    {
        return $this->cache;
    }

    public function getLogger()
    {
        return $this->logger;
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

    public function getDisableTestAccess()
    {
        return $this->disableTestAccess;
    }

    public function getGenomicsStartTime()
    {
        return $this->genomicsStartTime;
    }

    public function setConfigs()
    {
        if (!empty($this->config['rdr_endpoint'])) {
            $this->endpoint = $this->config['rdr_endpoint'];
        }
        if (!empty($this->config['rdr_disable_cache']) && $this->config['rdr_disable_cache']) {
            $this->cacheEnabled = $this->config['rdr_disable_cache'];
        }
        if (!empty($this->config['cache_time'])) {
            $this->cacheTime = $this->config['cache_time'];
        }
        if (!empty($this->config['disable_test_access'])) {
            $this->disableTestAccess = $this->config['disable_test_access'];
        }
        if (!empty($this->config['genomics_start_time'])) {
            $this->genomicsStartTime = $this->config['genomics_start_time'];
        }
    }

    public function getSiteType($awardeeId)
    {
        $site = $this->em->getRepository('sites')->fetchOneBy(['awardee_id' => $awardeeId]);
        if (!empty($site)) {
            return strtolower($site['type']) === 'dv' ? 'dv' : 'hpo';
        }
        return null;
    }
}
