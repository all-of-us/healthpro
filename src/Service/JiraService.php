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

    private ?Client $client = null;

    public function __construct(ParameterBagInterface $params, LoggerInterface $logger)
    {
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
     * @return list<object>
     */
    public function getVersions(int $count = 10): array
    {
        $endpoint = sprintf('project/%s/version', self::SOURCE_PROJECT_KEY);
        $response = $this->getClient()->request('GET', $endpoint, [
            'query' => [
                'orderBy' => '-releaseDate',
                'maxResults' => $count,
                'expand' => 'issuesstatus'
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->values ?? [];
    }

    /**
     * @return list<object>
     */
    public function getIssuesByVersion(string $version): array
    {
        $jql = sprintf('project=%s AND fixVersion=%s', self::SOURCE_PROJECT_KEY, $version);
        $response = $this->getClient()->request('POST', 'search/jql', [
            'json' => [
                'jql' => $jql,
                'fields' => ['issuetype', 'status', 'summary', 'assignee']
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->issues ?? [];
    }

    /**
     * @param array<string, mixed> $description
     */
    public function createReleaseTicket(string $title, array $description, string $componentId): ?string
    {
        $fields = [
            'summary' => $title,
            'description' => $description,
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
        $response = $this->getClient()->request('POST', 'issue', [
            'json' => [
                'fields' => $fields
            ]
        ]);
        $responseObject = json_decode($response->getBody()->getContents());

        return $responseObject->key ?? null;
    }

    /**
     * @param string|array<string, mixed> $comment
     */
    public function createComment(string $ticketId, string|array $comment): bool
    {
        try {
            if (is_string($comment)) {
                $body = [
                    'type' => 'doc',
                    'version' => 1,
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $comment
                                ]
                            ]
                        ]
                    ]
                ];
            } else {
                $body = $comment;
            }
            $response = $this->getClient()->request('POST', "issue/{$ticketId}/comment", [
                'json' => [
                    'body' => $body
                ]
            ]);
            return $response->getStatusCode() === 201;
        } catch (\Exception $e) {
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
            $response = $this->getClient()->request('POST', "issue/{$ticketId}/attachments", [
                'headers' => $headers,
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => fopen($path, 'rb'),
                        'filename' => $fileName
                    ]
                ]
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return list<object>
     */
    public function getComponents(): array
    {
        $endpoint = sprintf('project/%s/components', self::DESTINATION_PROJECT_KEY);
        $response = $this->getClient()->request('GET', $endpoint);
        $responseObject = json_decode($response->getBody()->getContents());
        return $responseObject ?? [];
    }

    private function getClient(): Client
    {
        if ($this->client === null) {
            throw new \LogicException('Jira client is not configured.');
        }

        return $this->client;
    }
}
