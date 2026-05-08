<?php
namespace App\Tests\Service;

use App\Entity\User;
use App\Service\ContextTemplateService;

class ContextTemplateServiceTest extends ServiceTestCase
{
    protected $contextTemplateService;

    public function setUp(): void
    {
        parent::setUp();
        $this->contextTemplateService = static::getContainer()->get(ContextTemplateService::class);
    }

    public function testGetDocumentTitlesList(): void
    {
        foreach (User::PROGRAMS as $program) {
            $this->session->set('program', $program);
            $this->assertIsString($this->contextTemplateService->GetProgramTemplate('index.html.twig'));
            $this->assertMatchesRegularExpression("/program\/{$program}\/.*/", $this->contextTemplateService->GetProgramTemplate('index.html.twig'));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("programTemplateDataProvider")]
    public function testGetProgramTemplate(string $program, string $expectedTemplate): void
    {
        $this->session->set('program', $program);
        $getTemplate = $this->contextTemplateService->getProgramTemplate('template.html');
        $this->assertSame($expectedTemplate, $getTemplate);
    }

    public static function programTemplateDataProvider(): array
    {
        return [
            ['nph', 'program/nph/template.html'],
            ['hpo', 'program/hpo/template.html']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("isCurrentProgramDataProvider")]
    public function testIsCurrentProgram(string $program, bool $expectedIsCurrentProgram): void
    {
        $this->session->set('program', $program);
        $isCurrentProgram = $this->contextTemplateService->isCurrentProgram($program);
        $this->assertSame($expectedIsCurrentProgram, $isCurrentProgram);
    }

    public static function isCurrentProgramDataProvider(): array
    {
        return [
            ['nph', true],
            ['hpo', true]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("getCurrentProgramDataProvider")]
    public function testGetCurrentProgram(string $program): void
    {
        $this->session->set('program', $program);
        $currentProgram = $this->contextTemplateService->getCurrentProgram();
        $this->assertSame($currentProgram, $program);
    }

    public static function getCurrentProgramDataProvider(): array
    {
        return [
            ['nph'],
            ['hpo']
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("getIsCurrentProgramHpoDataProvider")]
    public function testIsCurrentProgramHpo(string $program, bool $expectedIsCurrentProgramHpo): void
    {
        $this->session->set('program', $program);
        $isCurrentProgramHpo = $this->contextTemplateService->isCurrentProgramHpo();
        $this->assertSame($isCurrentProgramHpo, $expectedIsCurrentProgramHpo);
    }

    public static function getIsCurrentProgramHpoDataProvider(): array
    {
        return [
            ['nph', false],
            ['hpo', true]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("getIsCurrentProgramNphDataProvider")]
    public function testIsCurrentProgramNph(string $program, bool $expectedIsCurrentProgramNph): void
    {
        $this->session->set('program', $program);
        $isCurrentProgramNph = $this->contextTemplateService->isCurrentProgramNph();
        $this->assertSame($isCurrentProgramNph, $expectedIsCurrentProgramNph);
    }

    public static function getIsCurrentProgramNphDataProvider(): array
    {
        return [
            ['nph', true],
            ['hpo', false]
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider("getCurrentProgramDisplayTextDataProvider")]
    public function testGetCurrentProgramDisplayText(string $program, string $expectedIsCurrentProgramDisplayText): void
    {
        $this->session->set('program', $program);
        $currentProgramDisplayText = $this->contextTemplateService->getCurrentProgramDisplayText();
        $this->assertSame($currentProgramDisplayText, $expectedIsCurrentProgramDisplayText);
    }

    public static function getCurrentProgramDisplayTextDataProvider(): array
    {
        return [
            ['nph', 'NPH'],
            ['hpo', 'All of Us']
        ];
    }
}
