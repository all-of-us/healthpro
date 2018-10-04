<?php

namespace Pmi\Console\Command;

use Pmi\Application\HpoApplication;
use Pmi\Drc\RdrMetrics;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Metrics Command
 */
class MetricsCommand extends Command
{
    // Allowed stratfications in RDR
    const STRATIFICATIONS = ['TOTAL', 'ENROLLMENT_STATUS'];

    protected function configure()
    {
        $this
            ->setName('pmi:metrics')
            ->addOption(
                'start_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range beginning.',
                date('Y-m-d', strtotime('today - 7 days'))
            )
            ->addOption(
                'end_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range ending.',
                date('Y-m-d')
            )
            ->addOption(
                'stratification',
                null,
                InputOption::VALUE_REQUIRED,
                'How to stack the returned data.',
                'ENROLLMENT_STATUS'
            )
            ->addOption(
                'centers',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter to specified centers (awardees)'
            )
            ->addOption(
                'statuses',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter to specified statuses.'
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_OPTIONAL,
                'Pretty formatting for JSON ouput',
                false
            )
            ->setDescription('Get current metrics from the RDR.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->setFormatter(new OutputFormatter(true));

        $start_date = $input->getOption('start_date');
        $end_date = $input->getOption('end_date');
        $stratification = $input->getOption('stratification');
        $centers = join(',', $input->getOption('centers'));
        $statuses = join(',', $input->getOption('statuses'));
        $pretty = ($input->getOption('pretty') !== false) ? JSON_PRETTY_PRINT : false;

        // Validate stratification
        if (!in_array($stratification, self::STRATIFICATIONS)) {
            $output->writeln(sprintf(
                '<error>Invalid stratification: "%s"; Valid options: %s</error>',
                $stratification,
                join(', ', self::STRATIFICATIONS)
            ));
            exit(1);
        }

        // Validate start and end dates
        if ((bool) !strtotime($start_date) || (bool) !strtotime($end_date)) {
            $output->writeln(sprintf(
                '<error>Invalid dates: "%s", "%s"</error>',
                $start_date,
                $end_date
            ));
            exit(1);
        }

        putenv('PMI_ENV=' . HpoApplication::ENV_LOCAL);
        $app = new HpoApplication([
            'isUnitTest' => true,
            'debug' => true
        ]);
        $app->setup();

        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        $data = $metricsApi->metrics2($start_date, $end_date, $stratification, $centers, $statuses);

        $output->writeln(json_encode($data, $pretty));
    }
}
