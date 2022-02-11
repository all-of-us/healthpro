<?php

namespace App\SecretManager;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;
use Google_Client as GoogleClient;

class SecretManager
{
    private const SECRET_NAME_CREDENTIALS = 'credentials';
    private const SECRET_NAME_GA_AUTH_JSON = 'gaAuthJson';
    private const DEV_PROJECT_ID = 'pmi-hpo-dev';

    private $projectId;
    private $secretManagerClient;

    public function __construct($useDefaultCredentials)
    {
        if (!$useDefaultCredentials) {
            $basePath = realpath(__DIR__ . '/../../');
            if (file_exists($basePath . '/dev_config/rdr_key.json')) {
                $file = $basePath . '/dev_config/rdr_key.json';
            }
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $file);

            $googleClient = new GoogleClient();
            $googleClient->useApplicationDefaultCredentials();
            $this->projectId = self::DEV_PROJECT_ID;
        } else {
            $this->projectId = getenv('GOOGLE_CLOUD_PROJECT');
        }
        $this->secretManagerClient = new SecretManagerServiceClient();
    }

    public function getSecrets()
    {
        $secrets = json_decode($this->getSecretPayload(self::SECRET_NAME_CREDENTIALS), true);
        $secrets[self::SECRET_NAME_GA_AUTH_JSON] = $this->getSecretPayload(self::SECRET_NAME_GA_AUTH_JSON);
        return $secrets;
    }

    public function getSecretPayload($secretName)
    {
        try {
            $name = $this->secretManagerClient::secretVersionName($this->projectId, $secretName, 'latest');
            $response = $this->secretManagerClient->accessSecretVersion($name);
            return $response->getPayload()->getData();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
