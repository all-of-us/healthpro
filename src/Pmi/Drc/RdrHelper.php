<?php
namespace Pmi\Drc;

class RdrHelper
{
    protected $client;
    protected $endpoint = 'https://pmi-rdr-api-test.appspot.com/_ah/api/';
    protected $options = [];

    public function __construct(array $options)
    {
        if (!empty($options)) {
            if (!empty($options['endpoint'])) {
                $this->endpoint = $options['endpoint'];
            }
            $this->options = $options;
        }
    }

    public function getClient($resourceEndpoint = null)
    {
        if (!empty($this->options['key_file'])) {
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $this->options['key_file']);
        }

        $googleClient = new \Google_Client();
        $googleClient->useApplicationDefaultCredentials();
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
}
