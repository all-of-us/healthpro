<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Session;
use Twig\Extension\RuntimeExtensionInterface;

class ContextTemplateService implements RuntimeExtensionInterface
{
    private Session\SessionInterface $session;
    public function __construct(Session\SessionInterface $session)
    {
        $this->session = $session;
    }
    public function GetProgramTemplate(string $RelativePath): string
    {
        return 'program/' . $this->session->get('program', 'hpo') . '/' . $RelativePath;
    }
}
