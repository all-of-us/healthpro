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
 *
 * Allows debugging local with the RDR Metrics API endpoint
 */
class MetricsEHRCommand extends Command
{

    /**
     * @var array
     */
    private static $MODES = ['ParticipantsOverTime', 'SitesActiveOverTime', 'Sites'];

    /**
     * @var array
     */
    private static $INTERVALS = ['week', 'month', 'quarter'];

    /**
     * Configure
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('pmi:metricsehr')
            ->addOption(
                'start_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range beginning.',
                date('Y-m-d', strtotime('today - 30 days'))
            )
            ->addOption(
                'end_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range ending.',
                date('Y-m-d')
            )
            ->addOption(
                'interval',
                null,
                InputOption::VALUE_REQUIRED,
                'Interval of reporting.',
                'week'
            )
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'Reporting mode.',
                null
            )
            ->addOption(
                'centers',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter to specified centers (awardees)'
            )
            ->addOption(
                'pretty',
                null,
                InputOption::VALUE_NONE,
                'Pretty formatting for JSON ouput'
            )
            ->addOption(
                'debug',
                null,
                InputOption::VALUE_NONE,
                'Show request debug'
            )
            ->setDescription('Get current EHR metrics from the RDR.')
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
        $interval = $input->getOption('interval');
        $mode = $input->getOption('mode');
        $centers = $input->getOption('centers');
        $pretty = ($input->getOption('pretty') !== false) ? JSON_PRETTY_PRINT : 0;
        $params = [];

        // Validate interval
        if (!in_array($interval, self::$INTERVALS)) {
            $output->writeln(sprintf(
                '<error>Invalid interval: "%s"; Valid options: %s</error>',
                $interval,
                join(', ', self::$INTERVALS)
            ));
            // Throw a non-zero exit status
            return 1;
        }

        // Validate mode
        if (!is_null($mode) && !in_array($mode, self::$MODES)) {
            $output->writeln(sprintf(
                '<error>Invalid mode: "%s"; Valid options: %s</error>',
                $mode,
                join(', ', self::$MODES)
            ));
            // Throw a non-zero exit status
            return 1;
        }

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

        if ($input->getOption('debug')) {
            $output->writeln('<info>Debugging Information</info>');
            $output->writeln('  Mode:                  ' . $mode);
            $output->writeln('  Start Date:            ' . $start_date);
            $output->writeln('  End Date:              ' . $end_date);
            $output->writeln('  Interval:              ' . $interval);
            $output->writeln('  Centers:               ' . json_encode($centers));
            $output->writeln('  Additional Parameters: ' . json_encode($params));
            $output->writeln('');
        }

        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        $data = $metricsApi->ehrMetrics($mode, $start_date, $end_date, $interval, $centers, $params);

        $output->writeln(json_encode($data, $pretty));
    }
}
