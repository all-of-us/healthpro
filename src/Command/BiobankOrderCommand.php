<?php

namespace App\Command;

use App\Service\EnvironmentService;
use App\Service\OrderService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class BiobankOrderCommand extends Command
{
    private $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
        parent::__construct();
    }


    protected function configure(): void
    {
        $this
            ->setName('pmi:biobank')
            ->addOption(
                'start_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range beginning',
                date('Y-m-d', strtotime('today - 7 days'))
            )
            ->addOption(
                'end_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range ending',
                date('Y-m-d')
            )
            ->addOption(
                'kit_id',
                null,
                InputOption::VALUE_REQUIRED,
                'Kit ID',
                null
            )
            ->addOption(
                'participant_id',
                null,
                InputOption::VALUE_REQUIRED,
                'Participant ID',
                null
            )
            ->addOption(
                'origin',
                null,
                InputOption::VALUE_REQUIRED,
                'Order origin.',
                null
            )
            ->addOption(
                'page_size',
                null,
                InputOption::VALUE_REQUIRED,
                'Number of results to return',
                10
            )
            ->addOption(
                'page',
                null,
                InputOption::VALUE_REQUIRED,
                'Page number of results to return',
                1
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Pretty formatting for JSON ouput'
            )
            ->setDescription('Get latest orders from the Biobank.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->setFormatter(new OutputFormatter(true));
        $start_date = $input->getOption('start_date');
        $end_date = $input->getOption('end_date');
        $participant_id = $input->getOption('participant_id');
        $kit_id = $input->getOption('kit_id');
        $origin = $input->getOption('origin');
        $page = $input->getOption('page');
        $pageSize = $input->getOption('page_size');
        $pretty = ($input->getOption('pretty') !== false) ? JSON_PRETTY_PRINT : 0;
        // Validate start and end dates
        if ((bool) !strtotime($start_date) || (bool) !strtotime($end_date)) {
            $output->writeln(sprintf(
                '<error>Invalid dates: "%s", "%s"</error>',
                $start_date,
                $end_date
            ));
            // Throw a non-zero exit status
            return 1;
        }
        putenv('PMI_ENV=' . EnvironmentService::ENV_LOCAL);
        $data = $this->orderService->getOrders([
            'participant_id' => $participant_id,
            'startDate' => $start_date,
            'endDate' => $end_date,
            'origin' => $origin,
            'kitId' => $kit_id,
            'page' => $page,
            'pageSize' => $pageSize
        ]);
        $output->writeln(json_encode($data, $pretty));
        return 0;
    }
}
