<?php

namespace App\Service;

use Exception;

class EnvironmentService
{
    const ENV_LOCAL = 'local'; // development environment (local GAE SDK)
    const ENV_DEV = 'dev';   // development environment (deployed to GAE)
    const ENV_STAGING = 'staging';  // staging environment
    const ENV_STABLE = 'stable';  // security testing / training environment
    const ENV_PROD = 'prod';  // production environment
    const DEFAULT_TIMEZONE = 'America/New_York';
    const DATASTORE_EMULATOR_HOST = 'localhost:8081';

    protected $name;
    protected $configuration = [];
    public $values = [];

    public static $timezoneOptions = [
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Mountain Time - Arizona',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time'
    ];

    public function __construct(array $values = [])
    {
        if (!array_key_exists('env', $values)) {
            $this->values['env'] = $this->determineEnv();
        }
        if (!array_key_exists('release', $values)) {
            $this->values['release'] = getenv('PMI_RELEASE') === false ?
                date('YmdHis') : getenv('PMI_RELEASE');
        }
        if (!array_key_exists('isUnitTest', $values)) {
            $this->values['isUnitTest'] = false;
        }
        if (!array_key_exists('debug', $values)) {
            $this->values['debug'] = ($this->values['env'] === self::ENV_LOCAL && !$this->values['isUnitTest']);
        }
        $this->values['assetVer'] = $this->values['env'] === self::ENV_LOCAL ?
            date('YmdHis') : $this->values['release'];
    }

    /** Determines the environment under which the code is running. */
    private function determineEnv()
    {
        $env = getenv('PMI_ENV');
        if ($env == self::ENV_LOCAL) {
            return self::ENV_LOCAL;
        } elseif ($env == self::ENV_DEV) {
            return self::ENV_DEV;
        } elseif ($env == self::ENV_STABLE) {
            return self::ENV_STABLE;
        } elseif ($env == self::ENV_STAGING) {
            return self::ENV_STAGING;
        } elseif ($env == self::ENV_PROD) {
            return self::ENV_PROD;
        } elseif ($this->isPhpDevServer()) {
            return self::ENV_LOCAL;
        } else {
            throw new Exception("Bad environment: $env");
        }
    }

    public function isLocal()
    {
        return $this->values['env'] === self::ENV_LOCAL;
    }

    public function isDev()
    {
        return $this->values['env'] === self::ENV_DEV;
    }

    public function isStable()
    {
        return $this->values['env'] === self::ENV_STABLE;
    }

    public function isStaging()
    {
        return $this->values['env'] === self::ENV_STAGING;
    }

    public function isProd()
    {
        return $this->values['env'] === self::ENV_PROD;
    }

    public function isPhpDevServer()
    {
        return
            isset($_SERVER['SERVER_SOFTWARE']) &&
            preg_match('/^PHP [0-9\\.]+ Development Server$/', $_SERVER['SERVER_SOFTWARE']);
    }
}
