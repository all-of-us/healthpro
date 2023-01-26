<?php

namespace App\Twig;

use App\Service\ContextTemplateService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ContextLoaderExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('LoadProgramTemplate', [ContextTemplateService::class, 'GetProgramTemplate']),
        ];
    }
}
