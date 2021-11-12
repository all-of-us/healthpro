<?php

namespace App\Mail;

use App\HttpClient;

class Mandrill
{
    private $apiKey;
    private $client;

    public function __construct($apiKey)
    {
        $this->apiKey = $apiKey;
        $this->client = new HttpClient([
            'base_uri' => 'https://mandrillapp.com/api/1.0/',
            'timeout' => 30
        ]);
    }

    protected function post($endpoint, array $data = [])
    {
        $data['key'] = $this->apiKey;
        $response = $this->client->request('POST', $endpoint, [
            'json' => $data
        ]);
        return json_decode($response->getBody()->getContents());
    }

    public function ping()
    {
        $result = $this->post('users/ping.json');
        return $result === 'PING!';
    }

    public function send(array $to, $from, $subject, $content, $tags = null)
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
}
