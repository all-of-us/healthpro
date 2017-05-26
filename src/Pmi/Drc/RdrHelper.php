<?php
namespace Pmi\Drc;

class RdrHelper
{
    protected $client;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
    protected $options = [];
    protected $cacheEnabled = true;
    protected $lastError;

    public function __construct(array $options)
    {
        if (!empty($options)) {
            if (!empty($options['endpoint'])) {
                $this->endpoint = $options['endpoint'];
            }
            if (!empty($options['disable_cache']) && $options['disable_cache']) {
                $this->cacheEnabled = false;
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
        return $googleClient->authorize(new \GuzzleHttp\Client([
            'base_uri' => $endpoint
        ]));
    }

    public function isCacheEnabled()
    {
        return $this->cacheEnabled;
    }

    public function logException(\Exception $e)
    {
        syslog(LOG_CRIT, $e->getMessage());
        $this->lastError = $e->getMessage();
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $responseCode = $response->getStatusCode();
            $contents = $response->getBody()->getContents();
            syslog(LOG_INFO, "Response code: {$responseCode}");
            syslog(LOG_INFO, "Response body: {$contents}");
            $this->lastError = $contents;
        }
    }

    public function getLastError()
    {
        return $this->lastError;
    }
}
