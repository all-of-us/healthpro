<?php

namespace App\Mail;

use App\HttpClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

class Sendgrid
{
    private HttpClient $client;

    public function __construct(string $apiKey, private LoggerInterface $logger)
    {
        $this->client = new HttpClient([
            'base_uri' => 'https://api.sendgrid.com/v3/',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * @param list<string> $to
     * @param list<string>|null $tags
     */
    public function send(array $to, string $from, string $subject, string $content, ?array $tags = null): bool
    {
        $data = [
            'personalizations' => [
                [
                    'to' => array_map(function ($email) {
                        return ['email' => $email];
                    }, $to),
                    'subject' => $subject,
                ]
            ],
            'from' => ['email' => $from],
            'content' => [
                [
                    'type' => 'text/plain',
                    'value' => $content
                ]
            ]
        ];

        if (!empty($tags)) {
            $data['personalizations'][0]['categories'] = $tags;
        }

        try {
            $response = $this->post('mail/send', $data);
            if ($response->getStatusCode() === 202) {
                return true;
            }
            $this->logger->error('SendGrid API error', [
                'status' => $response->getStatusCode(),
                'body' => $response->getBody()->getContents(),
                'recipients' => $to,
                'subject' => $subject
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('SendGrid exception: ' . $e->getMessage(), ['recipients' => $to, 'subject' => $subject]);
            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function post(string $endpoint, array $data = []): ResponseInterface
    {
        return $this->client->request('POST', $endpoint, [
            'json' => $data
        ]);
    }
}
