<?php

use App\Service\EnvironmentService;
use App\Service\HelpService;

$env = new EnvironmentService();

$container->loadFromExtension('twig', [
    'globals' => [
        'isStable' => $env->isStable(),
        'reportKitUrl' => $env->configuration['reportKitUrl'] ?? '',
        'assetVer' => $env->values['assetVer'],
        'awardeeOrganization' => '',
        'google_analytics_property' => $env->configuration['google_analytics_property'] ?? '',
        'sessionTimeout' => $env->values['sessionTimeOut'],
        'sessionWarning' => $env->values['sessionWarning'],
        'timeZones' => $env->getTimeZones(),
        'confluenceResources' => HelpService::$confluenceResources,
        'orderSampleInfo' => $env->configuration['order_samples_info_3']
    ],
]);
