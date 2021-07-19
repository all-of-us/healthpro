<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Google_Client as GoogleClient;
use Google_Service_Directory as GoogleDirectory;
use Google_Service_Exception as GoogleException;

class GoogleGroupsService
{
    const RETRY_LIMIT = 5;
    private $domain;
    private $client;

    public function __construct(ContainerBagInterface $params, EnvironmentService $env) {
        if (!$env->values['isUnitTest']) {
            $applicationName = $params->get('gaApplicationName');
            $adminEmail = $params->get('gaAdminEmail');
            $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/googleapps_key.json';
            if ($env->isLocal() && file_exists($keyFile)) {
                $authJson = file_get_contents($keyFile);
            } else {
                $authJson = $params->get('gaAuthJson');
            }
            $client = new GoogleClient();
            $client->setApplicationName($applicationName);
            $client->setAuthConfig(json_decode($authJson, true));
            $client->setSubject($adminEmail);
            $client->setScopes(GoogleDirectory::ADMIN_DIRECTORY_GROUP_READONLY);
            $this->client = new GoogleDirectory($client);
            $this->domain = $params->get('gaDomain');
        }
    }

    /**
     * Executes an API call, automatically retrying in cases where we are
     * being rate-limited.
     */
    private function callApi(string $resourceName, string $methodName, array $params)
    {
        $resource = $this->client->$resourceName;
        $method = new \ReflectionMethod(get_class($resource), $methodName);
        $doRetry = false; $retryCount = 0;
        do {
            try {
                $response = $method->invokeArgs($resource, $params);
                $doRetry = false;
            }
            catch (\Exception $e) {
                // implies a rate-limiting error that we should retry
                if ($e->getCode() == 403 && $retryCount < self::RETRY_LIMIT) {
                    $micros = self::calculateBackoff($retryCount);
                    error_log("$resourceName.$methodName was rate-limited; " .
                        "retrying in " . (round($micros / 1000000, 3)) .
                        " seconds...");
                    usleep($micros);
                    $doRetry = true;
                    $retryCount++;
                }
                else { throw $e; }
            }
        } while ($doRetry);
        return $response;
    }

    /**
     * Calculates the amount of time (in microseconds) to sleep after a failed API call as
     * specified by Google's recommended "exponential backoff" algorithm.
     * @see https://developers.google.com/drive/v3/web/handle-errors#exponential-backoff
     */
    private static function calculateBackoff(int $retryCount): int
    {
        $seconds = pow(2, $retryCount);
        $millis = mt_rand(1, 999);

        return $seconds * 1000000 + $millis * 1000;
    }

    /** Gets all groups to which a user belongs (or all groups if no user). */
    public function getGroups(string $userEmail): array
    {
        $groups = [];
        $nextToken = null;
        $email = "@{$this->domain}";
        do {
            $params = [
                'domain' => $this->domain,
                'userKey' => $userEmail
            ];
            if ($nextToken) {
                $params['pageToken'] = $nextToken;
            }
            $groupsCollection = $this->callApi('groups', 'listGroups', [$params]);
            $models = $groupsCollection->getGroups();
            if (is_array($models)) {
                // restrict groups to the configured (sub)domain (Google API includes *all* our groups)
                $domainModels = [];
                foreach ($models as $model) {
                    if (strcasecmp(substr($model->getEmail(), -strlen($email)), $email) === 0) {
                        $domainModels[] = $model;
                    }
                }
                $groups = array_merge($groups, $domainModels);
            }
            $nextToken = $groupsCollection->getNextPageToken();
        } while ($nextToken);

        return $groups;
    }

    /**
     * Returns the user's role in a group or null if not subscribed
     */
    public function getRole(string $userEmail, string $groupEmail): ?string
    {
        // Will throw a 4xx exception if the user is not in the group
        try {
            $result = $this->callApi('members', 'get', [$groupEmail, $userEmail]);
            if ($result) {
                return $result->getRole();
            } else {
                return null;
            }
        } catch (GoogleException $e) {
            return null;
        }
    }

    public function getMembers(string $groupEmail)
    {
        try {
            $result = $this->callApi('members', 'listMembers', [$groupEmail]);
            if ($result) {
                return $result->getMembers();
            } else {
                return null;
            }
        } catch (GoogleException $e) {
            return null;
        }
    }
}
