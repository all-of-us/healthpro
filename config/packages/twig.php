<?php

use App\Entity\User;
use App\Service\EnvironmentService;
use App\Service\HelpService;

$env = new EnvironmentService();

$container->loadFromExtension('twig', [
    'globals' => [
        'isStable' => $env->isStable(),
        'reportKitUrl' => $env->configuration['reportKitUrl'] ?? '',
        'assetVer' => $env->values['assetVer'],
        'siteInfo' => '@App\Service\SiteService',
        'google_analytics_property' => $env->configuration['google_analytics_property'] ?? '',
        'sessionTimeout' => $env->values['sessionTimeOut'],
        'sessionWarning' => $env->values['sessionWarning'],
        'timeZones' => $env->getTimeZones(),
        'confluenceResources' => HelpService::$confluenceResources,
        'feedback_url' => HelpService::getFeedbackUrl(),
        'nph_feedback_url' => HelpService::getNphFeedbackUrl(),
        'report_technical_issue_url' => HelpService::getReportTechnicalIssueUrl(),
        'userTimezones' => User::$timezones,
        'inactiveSiteFormDisabled' => 0,
        'nphResources' => HelpService::$nphResources
    ],
]);
