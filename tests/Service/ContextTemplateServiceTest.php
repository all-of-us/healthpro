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

    /**
     * @dataProvider programTemplateDataProvider
     */
    public function testGetProgramTemplate(string $program, string $expectedTemplate): void
    {
        $this->session->set('program', $program);
        $getTemplate = $this->contextTemplateService->getProgramTemplate('template.html');
        $this->assertSame($expectedTemplate, $getTemplate);
    }

    public function programTemplateDataProvider(): array
    {
        return [
            ['nph', 'program/nph/template.html'],
            ['hpo', 'program/hpo/template.html']
        ];
    }

    /**
     * @dataProvider isCurrentProgramDataProvider
     */
    public function testIsCurrentProgram(string $program, bool $expectedIsCurrentProgram): void
    {
        $this->session->set('program', $program);
        $isCurrentProgram = $this->contextTemplateService->isCurrentProgram($program);
        $this->assertSame($expectedIsCurrentProgram, $isCurrentProgram);
    }

    public function isCurrentProgramDataProvider(): array
    {
        return [
            ['nph', true],
            ['hpo', true]
        ];
    }

    /**
     * @dataProvider getCurrentProgramDataProvider
     */
    public function testGetCurrentProgram(string $program): void
    {
        $this->session->set('program', $program);
        $currentProgram = $this->contextTemplateService->getCurrentProgram();
        $this->assertSame($currentProgram, $program);
    }

    public function getCurrentProgramDataProvider(): array
    {
        return [
            ['nph'],
            ['hpo']
        ];
    }

    /**
     * @dataProvider getIsCurrentProgramHpoDataProvider
     */
    public function testIsCurrentProgramHpo(string $program, bool $expectedIsCurrentProgramHpo): void
    {
        $this->session->set('program', $program);
        $isCurrentProgramHpo = $this->contextTemplateService->isCurrentProgramHpo();
        $this->assertSame($isCurrentProgramHpo, $expectedIsCurrentProgramHpo);
    }

    public function getIsCurrentProgramHpoDataProvider(): array
    {
        return [
            ['nph', false],
            ['hpo', true]
        ];
    }

    /**
     * @dataProvider getIsCurrentProgramNphDataProvider
     */
    public function testIsCurrentProgramNph(string $program, bool $expectedIsCurrentProgramNph): void
    {
        $this->session->set('program', $program);
        $isCurrentProgramNph = $this->contextTemplateService->isCurrentProgramNph();
        $this->assertSame($isCurrentProgramNph, $expectedIsCurrentProgramNph);
    }

    public function getIsCurrentProgramNphDataProvider(): array
    {
        return [
            ['nph', true],
            ['hpo', false]
        ];
    }

    /**
     * @dataProvider getCurrentProgramDisplayTextDataProvider
     */
    public function testGetCurrentProgramDisplayText(string $program, string $expectedIsCurrentProgramDisplayText): void
    {
        $this->session->set('program', $program);
        $currentProgramDisplayText = $this->contextTemplateService->getCurrentProgramDisplayText();
        $this->assertSame($currentProgramDisplayText, $expectedIsCurrentProgramDisplayText);
    }

    public function getCurrentProgramDisplayTextDataProvider(): array
    {
        return [
            ['nph', 'NPH'],
            ['hpo', 'All of Us']
        ];
    }
}
