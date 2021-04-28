<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JiraService
{
    private $client;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        if (!$params->has('jira_api_user') || !$params->has('jira_api_token')) {
            $logger->warning('Missing JIRA API configuration. See config.yml.dist for details.');
            return;
        }
        $this->client = new Client([
            'base_uri' => 'https://precisionmedicineinitiative.atlassian.net/rest/api/latest/',
            'auth' => [$params->get('jira_api_user'), $params->get('jira_api_token')]
        ]);
    }

    public function getVersions(int $count = 10): array
    {
        $response = $this->client->request('GET', 'project/HPRO/version', [
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
        $jql = sprintf('project=%s AND fixVersion=%s', 'HPRO', $version);
        $response = $this->client->request('GET', 'search', [
            'query' => [
                'jql' => $jql,
                'fields' => 'issuetype,status,summary'
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->issues ?? [];
    }
}
