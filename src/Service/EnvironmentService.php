<?php

namespace App\Service;

use App\Datastore\Entities\Configuration;
use Exception;

class EnvironmentService
{
    public const ENV_LOCAL = 'local'; // development environment (local GAE SDK)
    public const ENV_DEV = 'dev';   // development environment (deployed to GAE)
    public const ENV_STAGING = 'staging';  // staging environment
    public const ENV_STABLE = 'stable';  // security testing / training environment
    public const ENV_PROD = 'prod';  // production environment
    public const DEFAULT_TIMEZONE = 'America/New_York';
    public const DATASTORE_EMULATOR_HOST = 'localhost:8081';
    public $configuration = [];
    public $values = [];

    public static $timezoneOptions = [
        'America/Puerto_Rico' => 'Atlantic Standard Time',
        'America/New_York' => 'Eastern Time',
        'America/Chicago' => 'Central Time',
        'America/Denver' => 'Mountain Time',
        'America/Phoenix' => 'Mountain Time - Arizona',
        'America/Los_Angeles' => 'Pacific Time',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time'
    ];

    public static array $hpoPpscDefaultEnvs = [
        'local' => 'dev',
        'dev' => 'dev',
        'staging' => 'qa',
        'stable' => 'uat',
        'prod' => 'prod'
    ];

    protected $name;

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
            $this->values['isUnitTest'] = $_SERVER['APP_ENV'] === 'test' ? true : false;
        }
        if (!array_key_exists('debug', $values)) {
            $this->values['debug'] = ($this->values['env'] === self::ENV_LOCAL && !$this->values['isUnitTest']);
        }
        $this->values['assetVer'] = $this->values['env'] === self::ENV_LOCAL ?
            date('YmdHis') : $this->values['release'];
        $this->values['sessionTimeOut'] = $this->isLocal() ? 3600 * 24 : 30 * 60;
        $this->values['sessionWarning'] = 2 * 60;
        if ($this->isLocal()) {
            putenv('DATASTORE_EMULATOR_HOST=' . self::DATASTORE_EMULATOR_HOST);
        }
        $this->loadConfiguration();
    }

    /** Determines the environment under which the code is running. */
    public function determineEnv()
    {
        $env = getenv('PMI_ENV') ?: $_SERVER['PMI_ENV'];
        if (empty($env)) {
            $env = 'local';
        }
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
        }
        throw new Exception("Bad environment: $env");
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

    public function getTimeZones()
    {
        return self::$timezoneOptions;
    }

    public function getPpscEnv($ppscEnv): ?string
    {
        return $ppscEnv ?: self::$hpoPpscDefaultEnvs[$this->values['env']];
    }

    protected function loadConfiguration($override = [])
    {
        // default two-factor setting
        $this->configuration['enforce2fa'] = $this->isProd();

        $appDir = realpath(__DIR__ . '/../../');
        $configFile = $appDir . '/dev_config/config.yml';
        if ($this->isLocal() && file_exists($configFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $configs = $yaml->parse(file_get_contents($configFile));
            if (is_array($configs) || count($configs) > 0) {
                foreach ($configs as $key => $val) {
                    $this->configuration[$key] = $val;
                }
            }
        }

        // circle ci db configurations
        $circleConfigFile = $appDir . '/ci/config.yml';
        if (getenv('CI') && $this->values['isUnitTest'] && file_exists($circleConfigFile)) {
            $yaml = new \Symfony\Component\Yaml\Parser();
            $configs = $yaml->parse(file_get_contents($circleConfigFile));
            if (is_array($configs) || count($configs) > 0) {
                foreach ($configs as $key => $val) {
                    $this->configuration[$key] = $val;
                }
            }
        }

        // unit tests don't have access to Datastore
        // local environment uses yaml file
        if (!$this->values['isUnitTest'] && !$this->isPhpDevServer() && !$this->isLocal()) {
            $configs = Configuration::fetchBy();
            foreach ($configs as $config) {
                $this->configuration[$config->key] = $config->value;
            }
        }

        foreach ($override as $key => $val) {
            $this->configuration[$key] = $val;
        }
    }
}
