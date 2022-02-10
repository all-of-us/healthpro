<?php

namespace App\SecretManager;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

class SecretManager
{
    private const SECRET_NAME_CREDENTIALS = 'credentials';
    private const SECRET_NAME_GA_AUTH_JSON = 'gaAuthJson';

    private $secretManagerClient;

    public function __construct()
    {
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
            $projectId = getenv('GOOGLE_CLOUD_PROJECT');
            $name = $this->secretManagerClient::secretVersionName($projectId, $secretName, 'latest');
            $response = $this->secretManagerClient->accessSecretVersion($name);
            return $response->getPayload()->getData();
        } catch (\Exception $e) {
            throw $e;
        }
    }
}
