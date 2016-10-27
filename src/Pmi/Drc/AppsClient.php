<?php
namespace Pmi\Drc;

use Pmi\Application\HpoApplication;

/**
 * Used to access data from Google Apps.
 */
class AppsClient
{
    /** Number of times we will retry an API call before failing. */
    const RETRY_LIMIT = 5;
    
    private $domain;
    private $client;
    private $directory;
    
    public static function createFromApp(HpoApplication $app)
    {
        $keyFile = realpath(__DIR__ . '/../../../') . '/dev_config/googleapps_key.json';
        if ($app->isLocal() && file_exists($keyFile)) {
            return new self($app->getConfig('gaApplicationName'), file_get_contents($keyFile), $app->getConfig('gaAdminEmail'), $app->getConfig('gaDomain'), $app->isLocal());
        } elseif ($app->getConfig('gaAuthJson')) {
            return new self($app->getConfig('gaApplicationName'), $app->getConfig('gaAuthJson'), $app->getConfig('gaAdminEmail'), $app->getConfig('gaDomain'), $app->isLocal());
        } else {
            return null;
        }
    }
    
    public function __construct($appName, $authJson, $adminEmail, $domain, $isLocal)
    {
        $this->domain = $domain;
        $this->client = new \Google_Client();
        $this->client->setApplicationName($appName);
        $this->client->setAuthConfig(json_decode($authJson, true));
        $this->client->setSubject($adminEmail);
        // http://stackoverflow.com/a/33838098/1402028
        if ($isLocal) {
            $this->client->setHttpClient(new \GuzzleHttp\Client(['verify'=>false]));
        }
        $this->client->setScopes(implode(' ', [
            \Google_Service_Directory::ADMIN_DIRECTORY_GROUP_READONLY
        ]));
        $this->directory = new \Google_Service_Directory($this->client);
    }
    
    /**
     * Executes an API call, automatically retrying in cases where we are
     * being rate-limited.
     * @param object $service the Google_Service_* being called.
     * @param string $resourceName the name of the Google_Service_*_Resource.
     * @param string $methodName the method to call on the resource.
     * @param array $params the parameters to pass to the method.
     * @return mixed the method's return value.
     */
    private function callApi($service, $resourceName, $methodName, $params)
    {
        $resource = $service->$resourceName;
        $method = new \ReflectionMethod(get_class($resource), $methodName);
        $doRetry = false; $retryCount = 0;
        do {
            try {
                $response = $method->invokeArgs($resource, $params);
                $doRetry = false;
            }
            catch (Exception $e) {
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
     * Calculates the amount of time to sleep after a failed API call as
     * specified by Google's recommended "exponential backoff" algorithm.
     * @see https://developers.google.com/drive/v3/web/handle-errors#exponential-backoff
     * @param int $retryCount the number of times we've retried.
     * @return the number of microseconds to sleep.
     */
    private static function calculateBackoff($retryCount)
    {
        $seconds = pow(2, $retryCount);
        $millis = mt_rand(1, 999);
        return $seconds * 1000000 + $millis * 1000;
    }
    
    /** Gets all groups to which a user belongs (or all groups if no user). */
    public function getGroups($userEmail = null)
    {
        $groups = [];
        $nextToken = null;
        do {
            $params = ['domain' => $this->domain];
            if ($userEmail) {
                $params['userKey'] = $userEmail;
            }
            if ($nextToken) {
                $params['pageToken'] = $nextToken;
            }
            $groupsCollection = $this->callApi($this->directory, 'groups', 'listGroups', [$params]);
            $models = $groupsCollection->getGroups();
            if (is_array($models)) {
                $groups = array_merge($groups, $models);
            }
            $nextToken = $groupsCollection->getNextPageToken();
        } while ($nextToken);
        return $groups;
    }
    
    /**
     * Checks if the given user is subscribed to the given group.
     * @param string $groupEmail the email address of the group.
     * @param string $userEmail the potential subscriber.
     * @return boolean true if the user is subscribed, false if not.
     */
    public function isSubscribed($groupEmail, $userEmail)
    {
        $subscribed = true;
        // we'll have an exception with a 404 if user isn't in the group
        try {
            $this->callApi($this->directory, 'members', 'get', array($groupEmail, $userEmail));
        }
        catch (\Google_Service_Exception $e) {
            if ($e->getCode() == 404) {
                $subscribed = false;
            } elseif ($e->getCode() == 400) {
                // also seeing "(400) Missing required field: memberKey" when
                // trying this on members who are not part of the group
                $subscribed = false;
            } else {
                throw $e;
            }
        }
        return $subscribed;
    }
}
