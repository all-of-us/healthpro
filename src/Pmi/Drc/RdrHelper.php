<?php
namespace Pmi\Drc;

use Pmi\HttpClient;
use Cache\Adapter\Memcache\MemcacheCachePool;

class RdrHelper
{
    protected $client;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $options = [];
    protected $cacheEnabled = true;
    protected $cacheTime = 300;
    protected $lastError;
    protected $disableTestAccess = false;

    public function __construct(array $options)
    {
        if (!empty($options)) {
            if (!empty($options['endpoint'])) {
                $this->endpoint = $options['endpoint'];
            }
            if (!empty($options['disable_cache']) && $options['disable_cache']) {
                $this->cacheEnabled = false;
            }
            if (!empty($options['cache_time'])) {
                $this->cacheTime  = $options['cache_time'];
            }
            if (!empty($options['disable_test_access'])) {
                $this->disableTestAccess  = $options['disable_test_access'];
            }
            $this->options = $options;
        }
    }

    public function getClient($resourceEndpoint = null)
    {
        $googleClient = new \Google_Client();

        if (!empty($this->options['key_contents'])) {
            $googleClient->setAuthConfig(json_decode($this->options['key_contents'], true));
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

        if (class_exists('Memcache')) {
            $client = new \Memcache();
            $cachePool = new MemcacheCachePool($client);

            $googleClient->setCache($cachePool);
        }

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

    public function logException(\Exception $e)
    {
        $this->lastError = $e->getMessage();
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            syslog(LOG_CRIT, $e->getMessage());
            $response = $e->getResponse();
            $responseCode = $response->getStatusCode();
            $contents = $response->getBody()->getContents();
            syslog(LOG_INFO, "Response code: {$responseCode}");
            syslog(LOG_INFO, "Response body: {$contents}");
            $this->lastError = $contents;
        } else {
            // No response - request probably timed out
            syslog(LOG_ERR, $e->getMessage());
        }
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getDisableTestAccess()
    {
        return $this->disableTestAccess;
    }
}
