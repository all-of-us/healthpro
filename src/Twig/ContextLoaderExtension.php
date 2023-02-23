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
            new TwigFunction('LoadProgramTemplate', [ContextTemplateService::class, 'getProgramTemplate']),
            new TwigFunction('isCurrentProgram', [ContextTemplateService::class, 'isCurrentProgram']),
            new TwigFunction('getCurrentProgram', [ContextTemplateService::class, 'getCurrentProgram']),
            new TwigFunction('isCurrentProgramHpo', [ContextTemplateService::class, 'isCurrentProgramHpo']),
            new TwigFunction('isCurrentProgramNph', [ContextTemplateService::class, 'isCurrentProgramNph']),
        ];
    }
}
