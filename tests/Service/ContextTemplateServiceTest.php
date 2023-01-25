<?php
namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ContextTemplateService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class ContextTemplateServiceTest extends ServiceTestCase
{
    protected $contextTemplateService;

    public function setUp(): void
    {
        $this->contextTemplateService = static::getContainer()->get(ContextTemplateService::class);
        $this->session = static::getContainer()->get(SessionInterface::class);
    }

    public function testGetDocumentTitlesList(): void
    {
        foreach (User::PROGRAMS as $program) {
            $this->session->set('program', $program);
            $this->assertIsString($this->contextTemplateService->GetProgramTemplate('index.html.twig'));
            $this->assertMatchesRegularExpression("/program\/{$program}\/.*/", $this->contextTemplateService->GetProgramTemplate('index.html.twig'));
        }
    }
}
