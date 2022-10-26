<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Extension\RuntimeExtensionInterface;

class ContextTemplateService implements RuntimeExtensionInterface
{
    private SessionInterface $session;

    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    public function GetProgramTemplate(string $RelativePath): string
    {
        return 'program/' . $this->session->get('program', 'hpo') . '/' . $RelativePath;
    }
}
