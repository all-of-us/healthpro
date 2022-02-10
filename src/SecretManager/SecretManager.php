<?php

namespace App\SecretManager;

use Google\Cloud\SecretManager\V1\SecretManagerServiceClient;

class SecretManager
{
    private $secretManagerClient;

    public function __construct()
    {
        $this->secretManagerClient = new SecretManagerServiceClient();
    }

    public function getSecrets()
    {
        $secrets = json_decode($this->getSecretPayload('credentials'), true);
        $secrets['gaAuthJson'] = $this->getSecretPayload('gaAuthJson');
        return $secrets;
    }

    public function getSecretPayload($secretName)
    {
        $name = $this->secretManagerClient::secretVersionName('pmi-hpo-dev', $secretName, 'latest');
        $response = $this->secretManagerClient->accessSecretVersion($name);
        return $response->getPayload()->getData();
    }
}
