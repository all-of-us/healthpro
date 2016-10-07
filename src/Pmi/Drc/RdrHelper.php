<?php
namespace Pmi\Drc;

class RdrHelper
{
    protected $client;
    protected $endpoint = 'https://pmi-drc-api-test.appspot.com/';
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
}
