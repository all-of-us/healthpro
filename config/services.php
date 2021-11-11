<?php

use App\Service\EnvironmentService;
use App\Datastore\Entities\Configuration;

$env = new EnvironmentService();

$appDir = realpath(__DIR__ . '/../');
$configFile = $appDir . '/dev_config/config.yml';
if ($env->isLocal() && file_exists($configFile)) {
    $yaml = new \Symfony\Component\Yaml\Parser();
    $configs = $yaml->parse(file_get_contents($configFile));
    if (is_array($configs) || count($configs) > 0) {
        foreach ($configs as $key => $val) {
            $container->setParameter($key, $val);
        }
    }
    // look for Docker environment variables override
    if (getenv('MYSQL_HOST')) {
        $container->setParameter('mysql_host', getenv('MYSQL_HOST'));
    }
    if (getenv('MYSQL_DATABASE')) {
        $container->setParameter('mysql_schema', getenv('MYSQL_DATABASE'));
    }
    if (getenv('MYSQL_USER')) {
        $container->setParameter('mysql_user', getenv('MYSQL_USER'));
    }
    if (getenv('MYSQL_PASSWORD') !== false) {
        $container->setParameter('mysql_password', getenv('MYSQL_PASSWORD'));
    }
}

// circle ci db configurations
$circleConfigFile = $appDir . '/ci/config.yml';
if (getenv('CI') && $env->values['isUnitTest'] && file_exists($circleConfigFile)) {
    $yaml = new \Symfony\Component\Yaml\Parser();
    $configs = $yaml->parse(file_get_contents($circleConfigFile));
    if (is_array($configs) || count($configs) > 0) {
        foreach ($configs as $key => $val) {
            $container->setParameter($key, $val);
        }
    }
}

// unit tests don't have access to Datastore
// local environment uses yaml file
if (!$env->values['isUnitTest'] && !$env->isPhpDevServer() && !$env->isLocal()) {
    $configs = Configuration::fetchBy();
    foreach ($configs as $config) {
        $container->setParameter($config->key, $config->value);
    }
}

if ($env->values['isUnitTest']) {
    $container->setParameter('local_mock_auth', true);
    $container->setParameter('gaBypass', false);
    $container->setParameter('ds_clean_up_limit', 100);
}
