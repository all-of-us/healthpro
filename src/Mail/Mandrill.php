<?php

namespace App\Mail;

use App\HttpClient;

class Mandrill
{
    private string $apiKey;
    private HttpClient $client;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new HttpClient([
            'base_uri' => 'https://mandrillapp.com/api/1.0/',
            'timeout' => 30
        ]);
    }

    public function ping(): bool
    {
        $result = $this->post('users/ping.json');
        return $result === 'PING!';
    }

    /**
     * @param list<string> $to
     * @param list<string>|null $tags
     */
    public function send(array $to, string $from, string $subject, string $content, ?array $tags = null): bool
    {
        $message = [
            'from_email' => $from,
            'text' => $content,
            'subject' => $subject,
            'tags' => $tags
        ];
        $recipients = [];
        foreach ($to as $email) {
            $recipients[] = ['email' => $email];
        }
        $message['to'] = $recipients;
        $result = $this->post('messages/send.json', [
            'message' => $message
        ]);

        return is_array($result)
            && isset($result[0])
            && isset($result[0]->status)
            && $result[0]->status === 'sent';
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function post(string $endpoint, array $data = []): mixed
    {
        $data['key'] = $this->apiKey;
        $response = $this->client->request('POST', $endpoint, [
            'json' => $data
        ]);
        return json_decode($response->getBody()->getContents());
    }
}
