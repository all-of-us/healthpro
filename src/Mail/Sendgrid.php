<?php

namespace App\Mail;

use App\HttpClient;
use Psr\Http\Message\ResponseInterface;

class Sendgrid
{
    private HttpClient $client;

    public function __construct(string $apiKey)
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

    public function send(array $to, string $from, string $subject, string $content, $tags = null): bool
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
            if ($response->getStatusCode() == 202) {
                return true;
            }
            error_log('SendGrid API Error: ' . $response->getStatusCode() . ' ' . $response->getBody()->getContents());
            return false;
        } catch (\Exception $e) {
            error_log('SendGrid Exception: ' . $e->getMessage());
            return false;
        }
    }

    protected function post(string $endpoint, array $data = []): ResponseInterface
    {
        return $this->client->request('POST', $endpoint, [
            'json' => $data
        ]);
    }
}
