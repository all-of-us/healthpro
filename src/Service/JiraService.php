<?php

namespace App\Service;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class JiraService
{
    public const INSTANCE_URL = 'https://precisionmedicineinitiative.atlassian.net';
    public const DESTINATION_COMPONENT_ID = '10074';
    private const SOURCE_PROJECT_KEY = 'HPRO';
    private const DESTINATION_PROJECT_KEY = 'PD';
    private const DESTINATION_ISSUE_TYPE_RELEASE = '10102'; // 10102 = release

    private $client;
    private $logger;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
        $this->logger = $logger;

        if (!$params->has('jira_api_user') || !$params->has('jira_api_token')) {
            $logger->warning('Missing Jira API configuration. See config.yml.dist for details.');
            return;
        }

        $this->client = new Client([
            'base_uri' => self::INSTANCE_URL . '/rest/api/3/',
            'auth' => [$params->get('jira_api_user'), $params->get('jira_api_token')]
        ]);
    }

    /**
     * Format plain text into Atlassian Document Format (ADF)
     */
    private function formatTextADF(string $text): array
    {
        return [
            'type' => 'doc',
            'version' => 1,
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'text' => $text,
                            'type' => 'text'
                        ]
                    ]
                ]
            ]
        ];
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

        $response = $this->client->request('POST', 'search/jql', [
            'json' => [
                'jql' => $jql,
                'fields' => ['issuetype', 'status', 'summary', 'assignee']
            ]
        ]);

        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->issues ?? [];
    }

    public function createReleaseTicket(string $title, string $description, string $componentId): ?string
    {
        $fields = [
            'summary' => $title,
            'description' => $this->formatTextADF($description),
            'project' => [
                'key' => self::DESTINATION_PROJECT_KEY
            ],
            'issuetype' => [
                'id' => self::DESTINATION_ISSUE_TYPE_RELEASE
            ]
        ];

        if ($componentId) {
            $fields['components'] = [
                ['id' => $componentId]
            ];
        }

        $response = $this->client->request('POST', 'issue', [
            'json' => [
                'fields' => $fields
            ]
        ]);

        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->key ?? null;
    }

    public function createComment(string $ticketId, string $comment): bool
    {
        try {
            $response = $this->client->request('POST', "issue/{$ticketId}/comment", [
                'json' => [
                    'body' => $this->formatTextADF($comment)
                ]
            ]);
            return $response && $response->getStatusCode() === 201;
        } catch (\Exception $e) {
            $this->logger->error("Failed to create Jira comment: " . $e->getMessage());
            return false;
        }
    }

    public function attachFile(string $ticketId, string $path, string $fileName): bool
    {
        try {
            $headers = [
                'Accept' => 'application/json',
                'X-Atlassian-Token' => 'no-check'
            ];
            $response = $this->client->request('POST', "issue/{$ticketId}/attachments", [
                'headers' => $headers,
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($path, 'rb'),
                        'filename' => $fileName
                    ]
                ]
            ]);
            return $response && $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            $this->logger->error("Failed to attach Jira file: " . $e->getMessage());
            return false;
        }
    }

    public function getComponents(): ?array
    {
        $endpoint = sprintf('project/%s/components', self::DESTINATION_PROJECT_KEY);
        $response = $this->client->request('GET', $endpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        return $responseObject ?? [];
    }
}
