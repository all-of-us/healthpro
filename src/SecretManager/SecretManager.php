<?php

namespace App\SecretManager;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

class SecretManager
{
    private const SECRET_NAME_CREDENTIALS = 'credentials';
    private const SECRET_NAME_GA_AUTH_JSON = 'gaAuthJson';
    private const DEV_PROJECT_ID = 'pmi-hpo-dev';

    private string $projectId;
    private SecretManagerServiceClient $secretManagerClient;

    public function __construct(bool $useDefaultCredentials)
    {
        if (!$useDefaultCredentials) {
            $basePath = realpath(__DIR__ . '/../../');
            $keyFile = $basePath . '/dev_config/rdr_key.json';
            if (!file_exists($keyFile)) {
                throw new \Exception("Couldn't find $keyFile");
            }
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keyFile);
            $this->projectId = self::DEV_PROJECT_ID;
        } else {
            $projectId = getenv('GOOGLE_CLOUD_PROJECT');
            if (!is_string($projectId) || $projectId === '') {
                throw new \RuntimeException('Missing GOOGLE_CLOUD_PROJECT environment variable.');
            }
            $this->projectId = $projectId;
        }
        $this->secretManagerClient = new SecretManagerServiceClient();
    }

    /**
     * @return array<string, mixed>
     */
    public function getSecrets(): array
    {
        $secrets = json_decode($this->getSecretPayload(self::SECRET_NAME_CREDENTIALS), true);
        if (!is_array($secrets)) {
            $secrets = [];
        }
        $secrets[self::SECRET_NAME_GA_AUTH_JSON] = $this->getSecretPayload(self::SECRET_NAME_GA_AUTH_JSON);
        return $secrets;
    }

    public function getSecretPayload(string $secretName): string
    {
        $name = SecretManagerServiceClient::secretVersionName($this->projectId, $secretName, 'latest');
        $response = $this->secretManagerClient->accessSecretVersion($name);

        return $response->getPayload()->getData();
    }
}
