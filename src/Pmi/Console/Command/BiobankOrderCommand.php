<?php

namespace Pmi\Console\Command;

use Pmi\Application\HpoApplication;
use Pmi\Drc\RdrParticipants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Metrics Command
 *
 * Allows debugging local with the RDR Metrics API endpoint
 */
class BiobankOrderCommand extends Command
{
    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
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
            ->setDescription('Get latest orders from the Biobank.')
        ;
    }

    /**
     * Execute
     *
     * @param IntputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
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

        putenv('PMI_ENV=' . HpoApplication::ENV_LOCAL);
        $app = new HpoApplication([
            'isUnitTest' => true,
            'debug' => true
        ]);
        $app->setup();

        $service = new RdrParticipants($app['pmi.drc.rdrhelper']);
        $data = $service->getOrders([
            'participant_id' => $participant_id,
            'startDate' => $start_date,
            'endDate' => $end_date,
            'origin' => $origin,
            'kitId' => $kit_id,
            'page' => $page,
            'pageSize' => $pageSize
        ]);

        $output->writeln(json_encode($data, $pretty));
    }
}
