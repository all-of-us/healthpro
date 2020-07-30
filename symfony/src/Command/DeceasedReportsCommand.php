<?php

namespace App\Command;

use App\Service\DeceasedReportsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeceasedReportsCommand extends Command
{
    protected static $defaultName = 'app:deceasedreports';
    protected $deceasedReportsService;

    public function __construct(DeceasedReportsService $deceasedReportsService)
    {
        $this->deceasedReportsService = $deceasedReportsService;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Interact with deceased reports.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Create DeceasedReport Observation
        $payload = [
            'status' => 'preliminary',
            'code' => [
                'text' => 'DeceasedReport'
            ],
            'effectiveDateTime' => '2020-04-01',
            'encounter' => [
                'reference' => 'OTHER',
                'display' => 'Testing API'
            ],
            'performer' => [
                [
                    'type' => 'https://www.pmi-ops.org/healthpro-username',
                    'reference' => 'user.name@pmi-ops.org'
                ]
            ],
            'issued' => date('c')
        ];
        // $json = $this->deceasedReportsService->createDeceasedReport('P556544510', $payload)->getBody();

        // Get list of DeceasedReports
        $reports = $this->deceasedReportsService->getDeceasedReports();
        $rows = [];
        foreach ($reports as $report) {
            $rows[] = [
                $report->identifier[0]->value,
                $report->effectiveDateTime,
                $report->subject->reference,
                $report->encounter->reference,
                $report->performer[0]->reference,
                $report->issued
            ];
        }
        $table = new Table($output);
        $table
            ->setHeaders(['Report ID', 'Participant ID', 'Date of Death', 'Reporting Method', 'Submitted By', 'Created'])
            ->setRows($rows)
        ;
        $table->render();

        return 0;
    }
}
