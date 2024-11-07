<?php

namespace App\Service;

use App\Security\User;
use Google\Client as GoogleClient;
use Google\Service\Directory as GoogleDirectory;
use Google\Service\Directory\Member as GoogleMember;
use Google\Service\Exception as GoogleException;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

class GoogleGroupsService
{
    public const RETRY_LIMIT = 5;
    private const MFA_EXCEPTION_GROUP = 'mfa_exception@pmi-ops.org';
    private $domain;
    private $client;

    public function __construct(ContainerBagInterface $params, EnvironmentService $env)
    {
        $gaBypass = $env->isLocal() && $params->has('gaBypass') && $params->get('gaBypass');
        if (!$env->values['isUnitTest'] && !$gaBypass) {
            $applicationName = $params->get('gaApplicationName');
            $adminEmail = $params->get('gaAdminEmail');
            $keyFile = realpath(__DIR__ . '/../../') . '/dev_config/googleapps_key.json';
            if ($env->isLocal() && file_exists($keyFile)) {
                $authJson = file_get_contents($keyFile);
            } else {
                $authJson = $params->get('gaAuthJson');
            }
            $client = new GoogleClient();
            $client->setApplicationName($applicationName);
            $client->setAuthConfig(json_decode($authJson, true));
            $client->setSubject($adminEmail);
            $client->setScopes([GoogleDirectory::ADMIN_DIRECTORY_GROUP, GoogleDirectory::ADMIN_DIRECTORY_USER_READONLY]);
            $this->client = new GoogleDirectory($client);
            $this->domain = $params->get('gaDomain');
        }
    }

    /** Gets all groups to which a user belongs (or all groups if no user). */
    public function getGroups(string $userEmail, $checkDomain = true): array
    {
        $groups = [];
        $nextToken = null;
        $email = "@{$this->domain}";
        do {
            $params = [
                'userKey' => $userEmail
            ];
            if ($checkDomain) {
                $params['domain'] = $this->domain;
            }
            if ($nextToken) {
                $params['pageToken'] = $nextToken;
            }
            $groupsCollection = $this->callApi('groups', 'listGroups', [$params]);
            $models = $groupsCollection->getGroups();
            if (is_array($models)) {
                // restrict groups to the configured (sub)domain (Google API includes *all* our groups)
                $domainModels = [];
                foreach ($models as $model) {
                    if (!$checkDomain || strcasecmp(substr($model->getEmail(), -strlen($email)), $email) === 0) {
                        $domainModels[] = $model;
                    }
                }
                $groups = array_merge($groups, $domainModels);
            }
            $nextToken = $groupsCollection->getNextPageToken();
        } while ($nextToken);

        // Only keep HPO admin & biobank user groups and all NPH groups
        $googleGroups = array_filter($groups, function ($group) {
            return str_starts_with($group->email, User::ADMIN_GROUP) ||
                str_starts_with($group->email, User::BIOBANK_GROUP) ||
                str_starts_with($group->email, User::NPH_TYPE);
        });

        return array_values($googleGroups);
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
            }
            return null;
        } catch (GoogleException $e) {
            return null;
        }
    }

    public function getMembers(string $groupEmail, $roles = [])
    {
        $params = [];
        if (!empty($roles)) {
            $params['roles'] = join(',', $roles);
        }
        try {
            $result = $this->callApi('members', 'listMembers', [$groupEmail, $params]);
            if ($result) {
                return $result->getMembers();
            }
            return [];
        } catch (GoogleException $e) {
            if ($e->getCode() === 404) {
                return [];
            }
            throw $e;
        }
    }

    public function getUser(string $user)
    {
        try {
            $result = $this->callApi('users', 'get', [$user]);
            if ($result) {
                return $result;
            }
            return null;
        } catch (GoogleException $e) {
            return null;
        }
    }

    public function getMemberById(string $groupEmail, string $memeberId)
    {
        try {
            return $this->callApi('members', 'get', [$groupEmail, $memeberId]);
        } catch (GoogleException $e) {
            return null;
        }
    }

    public function addMember(string $groupEmail, string $email)
    {
        try {
            $member = new GoogleMember();
            $member->setEmail($email);
            $member->setRole('MEMBER');
            $result = $this->callApi('members', 'insert', [$groupEmail, $member]);
            if ($result && $email === $result->getEmail()) {
                return [
                    'status' => 'success',
                    'message' => 'This member has been successfully added to the group.'
                ];
            }
            return [
                'status' => 'error',
                'message' => 'Error occurred. Please try again.'
            ];
        } catch (GoogleException $e) {
            return [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getErrors()[0]['message']
            ];
        }
    }

    public function removeMember(string $groupEmail, string $email)
    {
        try {
            $member = new GoogleMember();
            $member->setEmail($email);
            $member->setRole('MEMBER');
            $result = $this->callApi('members', 'delete', [$groupEmail, $email]);
            if ($result && $result->getStatusCode() === 204) {
                return [
                    'status' => 'success',
                    'message' => 'This member has been successfully removed from the group.'
                ];
            }
            return [
                'status' => 'error',
                'message' => 'Error occurred. Please try again.'
            ];
        } catch (GoogleException $e) {
            return [
                'status' => 'error',
                'message' => $e->getErrors()[0]['message']
            ];
        }
    }

    public function isMfaGroupUser($email): bool
    {
        return in_array(self::MFA_EXCEPTION_GROUP, $this->getUserGroupIds($email));
    }

    /**
     * Executes an API call, automatically retrying in cases where we are
     * being rate-limited.
     */
    private function callApi(string $resourceName, string $methodName, array $params)
    {
        $resource = $this->client->$resourceName;
        $method = new \ReflectionMethod(get_class($resource), $methodName);
        $doRetry = false;
        $retryCount = 0;
        do {
            try {
                $response = $method->invokeArgs($resource, $params);
                $doRetry = false;
            } catch (\Exception $e) {
                $message = json_decode($e->getMessage());
                $reason = isset($message->error->errors[0]->reason) ? $message->error->errors[0]->reason : 'unknown';
                if (in_array($e->getCode(), [403, 429]) && in_array($reason, ['userRateLimitExceeded', 'quotaExceeded', 'rateLimitExceeded']) && $retryCount < self::RETRY_LIMIT) {
                    $micros = self::calculateBackoff($retryCount);
                    error_log("$resourceName.$methodName was rate-limited; " .
                        'retrying in ' . (round($micros / 1000000, 3)) .
                        ' seconds...');
                    usleep($micros);
                    $doRetry = true;
                    $retryCount++;
                } else {
                    error_log($e->getCode() . '-' . $reason);
                    throw $e;
                }
            }
        } while ($doRetry);
        return !empty($response) ? $response : '';
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

    private function getUserGroupIds($email): array
    {
        $groups = $this->getGroups($email, false);
        $groupIds = [];
        foreach ($groups as $group) {
            $groupIds[] = $group->email;
        }
        return $groupIds;
    }
}
