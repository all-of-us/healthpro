<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface;

class ContextTemplateService implements RuntimeExtensionInterface
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getProgramTemplate(string $RelativePath): string
    {
        return 'program/' . $this->getProgram() . '/' . $RelativePath;
    }

    public function isCurrentProgram(string $program): bool
    {
        return $this->getProgram() === $program;
    }

    public function getCurrentProgram(): string
    {
        return $this->getProgram();
    }

    public function isCurrentProgramHpo(): bool
    {
        return $this->getProgram() === User::PROGRAM_HPO;
    }

    public function isCurrentProgramNph(): bool
    {
        return $this->getProgram() === User::PROGRAM_NPH;
    }

    public function getCurrentProgramDisplayText(): string
    {
        return $this->getProgram() === User::PROGRAM_NPH ? 'NPH' : 'All of Us';
    }

    private function getProgram(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            return $request->getSession()->get('program', User::PROGRAM_HPO);
        }

        return User::PROGRAM_HPO;
    }
}
