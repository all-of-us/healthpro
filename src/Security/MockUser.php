<?php

namespace App\Security;

class MockUser
{
    private int $id;
    private string $email;
    private ?string $timezone;

    public function __construct(string $email, ?string $timezone = null)
    {
        $this->email = $email;
        $this->timezone = $timezone;
        $this->id = hexdec(substr(sha1($email), 0, 8));
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function getUserId(): int
    {
        return $this->id;
    }
}
