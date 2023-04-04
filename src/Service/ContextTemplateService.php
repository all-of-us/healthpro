<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ContextTemplateService implements RuntimeExtensionInterface
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function getProgramTemplate(string $RelativePath): string
    {
        return 'program/' . $this->session->get('program', User::PROGRAM_HPO) . '/' . $RelativePath;
    }

    public function isCurrentProgram(string $program): bool
    {
        return $this->session->get('program', User::PROGRAM_HPO) === $program;
    }

    public function getCurrentProgram(): string
    {
        return $this->session->get('program', User::PROGRAM_HPO);
    }

    public function isCurrentProgramHpo(): bool
    {
        return $this->session->get('program', User::PROGRAM_HPO) === User::PROGRAM_HPO;
    }

    public function isCurrentProgramNph(): bool
    {
        return $this->session->get('program', User::PROGRAM_HPO) === User::PROGRAM_NPH;
    }

    public function getCurrentProgramDisplayText(): string
    {
        return $this->session->get('program') === User::PROGRAM_NPH ? 'NPH' : 'All of Us';
    }
}
