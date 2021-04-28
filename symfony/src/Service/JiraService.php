<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JiraService
{
    public const INSTANCE_URL = 'https://precisionmedicineinitiative.atlassian.net';

    private $client;
    private const SOURCE_PROJECT_KEY = 'HPRO';
    private const DESTINATION_PROJECT_KEY = 'PD';
    private const DESTINATION_ISSUE_TYPE_ID = '10000'; // 10000 = story

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        if (!$params->has('jira_api_user') || !$params->has('jira_api_token')) {
            $logger->warning('Missing JIRA API configuration. See config.yml.dist for details.');
            return;
        }
        $this->client = new Client([
            'base_uri' => self::INSTANCE_URL . '/rest/api/latest/',
            'auth' => [$params->get('jira_api_user'), $params->get('jira_api_token')]
        ]);
    }

    public function getVersions(int $count = 10): array
    {
        $endpoint = sprintf('project/%s/version', self::SOURCE_PROJECT_KEY);
        $response = $this->client->request('GET', $endpoint, [
            'query' => [
                'orderBy' => '-releaseDate',
                'maxResults' => $count,
                'expand' => 'issuesstatus'
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->values ?? [];
    }

    public function getIssuesByVersion(string $version): array
    {
        $jql = sprintf('project=%s AND fixVersion=%s', self::SOURCE_PROJECT_KEY, $version);
        $response = $this->client->request('GET', 'search', [
            'query' => [
                'jql' => $jql,
                'fields' => 'issuetype,status,summary'
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->issues ?? [];
    }

    public function createReleaseTicket(string $title, string $description): ?string
    {
        $response = $this->client->request('POST', 'issue', [
            'json' => [
                'fields' => [
                    'summary' => $title,
                    'description' => $description,
                    'project' => [
                        'key' => self::DESTINATION_PROJECT_KEY
                    ],
                    'issuetype' => [
                        'id' => self::DESTINATION_ISSUE_TYPE_ID
                    ]
                ]
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->key ?? null;
    }
}
