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
    private static $MODES = ['ParticipantsOverTime', 'Organizations'];

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
                'end_date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date range ending.',
                date('Y-m-d')
            )
            ->addOption(
                'mode',
                null,
                InputOption::VALUE_OPTIONAL,
                'Reporting mode.',
                null
            )
            ->addOption(
                'organizations',
                null,
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Filter to specified organizations'
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

        $end_date = $input->getOption('end_date');
        $mode = $input->getOption('mode');
        $organizations = $input->getOption('organizations');
        $pretty = ($input->getOption('pretty') !== false) ? JSON_PRETTY_PRINT : 0;

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
            $output->writeln('  Organizations:         ' . json_encode($organizations));
            $output->writeln('  Additional Parameters: ' . json_encode($params));
            $output->writeln('');
        }

        $metricsApi = new RdrMetrics($app['pmi.drc.rdrhelper']);
        $data = $metricsApi->ehrMetrics($mode, $end_date, $organizations);

        $output->writeln(json_encode($data, $pretty));
    }
}
