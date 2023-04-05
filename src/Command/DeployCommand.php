<?php

namespace App\Command;

use App\Service\EnvironmentService;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;

class DeployCommand extends Command
{
    /** GAE application IDs for production. */
    private static array $PROD_APP_IDS = [
        'healthpro-prod'
    ];

    /** GAE application IDs for security testing and/or training. */
    private static array $STABLE_APP_IDS = [
        'pmi-hpo-test'
    ];

    /** GAE application IDs for staging. */
    private static array $STAGING_APP_IDS = [
        'pmi-hpo-staging', // dry run environment
        'healthpro-beta', // beta environment
        'healthpro-staging' // staging environment
    ];

    /** Create release tag when deploying these application IDs. */
    private static array $TAG_APP_IDS = [
        'healthpro-prod',
        'pmi-hpo-test'
    ];

    /** Apply enhanced instance class and scaling for these application IDs. */
    private static array $SCALE_APP_IDS = [
        'healthpro-prod'
    ];

    // #@+ Config settings set by execute().
    private string $appDir;
    private ?string $appId;
    private bool $local;
    private bool $noInteraction;
    private int $port;
    private InputInterface $in;
    private OutputInterface $out;
    private string $release;

    /** Determines the environment we are deploying to. */
    public function determineEnv(): string
    {
        if ($this->local) {
            return EnvironmentService::ENV_LOCAL;
        } elseif ($this->isDev()) {
            return EnvironmentService::ENV_DEV;
        } elseif ($this->isStable()) {
            return EnvironmentService::ENV_STABLE;
        } elseif ($this->isStaging()) {
            return EnvironmentService::ENV_STAGING;
        } elseif ($this->isProd()) {
            return EnvironmentService::ENV_PROD;
        }
        throw new Exception('Unable to determine environment!');
    }

    protected function configure()
    {
        $appDir = realpath(__DIR__ . '/../..');
        $this
            ->setName('pmi:deploy')
            ->setDescription('PMI Build and Deploy')
            ->addOption(
                'appDir',
                'a',
                InputOption::VALUE_OPTIONAL,
                'Path to the application code',
                $appDir
            )
            ->addOption(
                'appId',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Google project application ID'
            )
            ->addOption(
                'local',
                'l',
                InputOption::VALUE_NONE,
                'If set, deploy locally to your GAE SDK web server'
            )
            ->addOption(
                'port',
                'p',
                InputOption::VALUE_OPTIONAL,
                'GAE SDK web server port',
                8080
            )
            ->addOption(
                'release',
                'r',
                InputOption::VALUE_OPTIONAL,
                'Release version used in tagging and cache busting',
                date('YmdHis')
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->in = $input;
        $this->out = $output;
        $this->out->setFormatter(new OutputFormatter(true)); // color output
        $this->appId = $input->getOption('appId');
        $this->local = (bool) $input->getOption('local');
        $this->noInteraction = (bool) $input->getOption('no-interaction');
        $this->port = (int) $input->getOption('port');
        $this->release = $input->getOption('release');

        if (!$this->appId && !$this->local) {
            throw new InvalidOptionException('Please specify --appId (-i) or --local');
        }

        $this->appDir = preg_replace('%[\\/]*$%', '', $input->getOption('appDir'));
        if (!file_exists($this->appDir) || !is_dir("$this->appDir")) {
            throw new InvalidOptionException("Bad application directory: {$this->appDir}");
        }
        // ensure shell commands (like `git`) are executed from inside repo
        chdir($this->appDir);

        // generate config files
        $this->generateAppConfig();
        $this->generatePhpConfig();
        $this->generateCronConfig();

        // If not local, compile assets. Run `npm run watch` when developing locally.
        if (!$this->local) {
            // ensure that we are up-to-date with the latest NPM dependencies
            $output->writeln('');
            $output->writeln('Checking NPM dependencies...');
            $this->exec('npm install --no-audit'); // npm audit will be run below in the runJsSecurityCheck method

            // compile (concat/minify/copy) assets
            $output->writeln('');
            $output->writeln('Compiling assets...');
            $this->exec('npm run build');
        }

        // security checks
        $this->runSecurityCheck();
        $this->out->writeln('');
        $this->runJsSecurityCheck(); // must occur after asset compilation

        // unit tests should pass before deploying to testers or production
        if ($this->isStaging() || $this->isStable() || $this->isProd()) {
            $this->runUnitTests();
        }

        if ($this->local) {
            $cmd = "php -c php.ini -S 0.0.0.0:{$this->port} -t web/ web/local-router.php";
        } else {
            $cmd = "gcloud app deploy --quiet --project={$this->appId} {$this->appDir}/app.yaml {$this->appDir}/cron.yaml";
        }
        $output->writeln('');
        $output->writeln("Deploy command: <info>{$cmd}</info>");
        $output->writeln('');

        // specify favicon
        $favicon = "{$this->appDir}/web/favicon.ico";
        if (file_exists($favicon)) {
            unlink($favicon); // clear the old one
        }
        $envFavicon = "{$this->appDir}/web/assets/favicon/favicon_" . $this->determineEnv() . '.ico';
        if (file_exists($envFavicon)) {
            copy($envFavicon, $favicon);
        }

        if ($this->local || $this->verifyDeploy($input, $output)) {
            $this->exec($cmd, true, true);
            if ($this->isTaggable()) {
                $this->tagRelease();
            }
            $output->writeln('');
            $output->writeln('All done 👍'); // emoji :thumbs-up:
            // taggable implies we are tracking/auditing
            if ($this->isTaggable()) {
                $output->writeln('');
                $output->writeln('<error>Remember to attach deploy output to Jira ticket!</error>');
            }
        } else {
            $output->writeln('');
            $output->writeln('Fine then, be that way 😾'); // emoji :pouting_cat:
        }
        return 0;
    }

    private function verifyDeploy(InputInterface $input, OutputInterface $output): bool
    {
        if ($this->noInteraction) {
            return true;
        }
        /** @var QuestionHelper $question */
        $question = $this->getHelper('question');
        $this->exec('git status'); // display git status
        $gitStatus = new ConfirmationQuestion(
            '<comment>Does git status look good? (y/n)</comment> ',
            false,
            '/^(y|yes)$/'
        );
        $destinationText = $this->isProd() ? ' <error>TO PRODUCTION</error>!' : ' to ' . $this->determineEnv();
        $reallyDeploy = new ConfirmationQuestion(
            "<question>Do you REALLY want to deploy{$destinationText}? (y/n)</question> ",
            false,
            '/^(y|yes)$/'
        );
        return $question->ask($input, $output, $gitStatus) && $question->ask($input, $output, $reallyDeploy);
    }

    private function isDev(): bool
    {
        return !$this->local && !$this->isStable() && !$this->isStaging() && !$this->isProd();
    }

    private function isStable(): bool
    {
        return !$this->local && in_array($this->appId, self::$STABLE_APP_IDS);
    }

    private function isStaging(): bool
    {
        return !$this->local && in_array($this->appId, self::$STAGING_APP_IDS);
    }

    private function isProd(): bool
    {
        return !$this->local && in_array($this->appId, self::$PROD_APP_IDS);
    }

    private function isTaggable(): bool
    {
        return !$this->local && in_array($this->appId, self::$TAG_APP_IDS);
    }

    /** Adds and pushes a release tag to git. */
    private function tagRelease()
    {
        $tag = "REL_{$this->appId}_{$this->release}";
        $this->out->writeln('');
        $this->out->writeln("<info>Setting release tag to {$tag}...</info>");
        $this->exec("git tag $tag");
        $this->out->writeln('<info>Pushing tag...</info>');
        $this->exec("git push origin $tag");
    }

    /** Generate app configuration. */
    private function generateAppConfig()
    {
        $configFile = $this->appDir . DIRECTORY_SEPARATOR . 'app.yaml';
        $distFile = "{$configFile}.dist";
        if (!file_exists($distFile)) {
            throw new Exception("Couldn't find $distFile");
        }

        $yaml = new Parser();
        $config = $yaml->parse(file_get_contents($distFile));

        // crash the deploy if our handlers are not secure
        $this->checkHandlerSecurity($config);

        // set environment variables
        $this->configureEnv($config);

        // enhance instance class and scaling
        if ($this->isProd() && in_array($this->appId, self::$SCALE_APP_IDS)) {
            $this->configureScaling($config);
        }

        $dumper = new Dumper();
        file_put_contents($configFile, $dumper->dump($config, PHP_INT_MAX));
    }

    /** Generate PHP configuration. */
    private function generatePhpConfig()
    {
        $configFile = $this->appDir . DIRECTORY_SEPARATOR . 'php.ini';
        $distFile = "{$configFile}.dist";
        if (!file_exists($distFile)) {
            throw new Exception("Couldn't find $distFile");
        }

        $ini = '';
        $contents = file_get_contents($distFile);
        $lines = explode("\n", $contents);
        foreach ($lines as $l) {
            $line = trim($l);
            if (strlen($line) === 0) {
                continue;
            } elseif (preg_match('/^display_errors\s*=/i', $line)) {
                $line = 'display_errors = ' . (($this->isProd() || $this->isStable() || $this->isStaging()) ? 0 : 1);
            } elseif (preg_match('/^error_reporting\s*=/i', $line)) {
                $line = 'error_reporting = ' . ($this->isProd() ?
                    'E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED' : 'E_ALL');
            } elseif (preg_match('/^session.cookie_secure\s*=/i', $line)) {
                $line = 'session.cookie_secure = ' . ($this->local ? 0 : 1);
            }
            $ini .= "{$line}\n";
        }

        file_put_contents($configFile, $ini);
    }

    /** Generate cron configuration. */
    private function generateCronConfig()
    {
        $configFile = $this->appDir . DIRECTORY_SEPARATOR . 'cron.yaml';
        $distFile = "{$configFile}.dist";
        if (!file_exists($distFile)) {
            throw new Exception("Couldn't find $distFile");
        }

        $yaml = new Parser();
        $config = $yaml->parse(file_get_contents($distFile));

        // adjust crons depending on environment
        $crons = [];
        foreach ($config['cron'] as $c) {
            if (stristr($c['url'], '/ping-test') !== false && $this->isProd()) {
                continue;
            }
            $crons[] = $c;
        }
        $config['cron'] = $crons;

        $dumper = new Dumper();
        file_put_contents($configFile, count($crons) ? $dumper->dump($config, PHP_INT_MAX) : 'cron:');
    }

    /** Check all handlers for security concerns. */
    private function checkHandlerSecurity($config)
    {
        $this->out->writeln('Checking URL handler security...');
        foreach ($config['handlers'] as $handler) {
            if (empty($handler['secure']) || $handler['secure'] !== 'always') {
                throw new Exception("Handler URL '{$handler['url']}' does not force SSL!");
            }
        }
        $this->out->writeln('... all ' . count($config['handlers']) . ' handlers are secure.');
    }

    /** Sets the app's environment variables. */
    private function configureEnv(&$config)
    {
        $config['env_variables']['PMI_ENV'] = $this->determineEnv();
        $config['env_variables']['PMI_RELEASE'] = $this->release;
        $config['env_variables']['APP_ENV'] = $this->local ? 'dev' : 'prod';
    }

    /** Enhance instance class and scaling */
    private function configureScaling(&$config)
    {
        $config['instance_class'] = 'F4'; // 512MB, 2.4 GHz
        // improve user experience during low traffic
        $config['automatic_scaling']['min_idle_instances'] = 2;
    }

    private function runUnitTests()
    {
        $this->exec("{$this->appDir}/bin/phpunit");
    }

    private function displayVulnerabilities($vulnerabilities)
    {
        // taken from SensioLabs\Security\Formatters\SimpleFormatter in 4.1 that was removed in 5.0
        $count = count($vulnerabilities);
        $this->out->writeln(sprintf('<error>[CRITICAL] %d %s known vulnerabilities</>', $count, 1 === $count ? 'package has' : 'packages have'));
        $this->out->writeln('');
        foreach ($vulnerabilities as $dependency => $issues) {
            $dependencyFullName = $dependency . ' (' . $issues['version'] . ')';
            $this->out->writeln('<info>' . $dependencyFullName . "\n" . str_repeat('-', strlen($dependencyFullName)) . "</>\n");
            foreach ($issues['advisories'] as $details) {
                $this->out->write(' * ');
                if ($details['cve']) {
                    $this->out->write('<comment>' . $details['cve'] . ': </comment>');
                }
                $this->out->writeln($details['title']);
                if ('' !== $details['link']) {
                    $this->out->writeln('   ' . $details['link']);
                }
                $this->out->writeln('');
            }
        }
    }

    private function runSecurityCheck()
    {
        $this->out->writeln('Running Symfony Security Check...');
        $process = Process::fromShellCommandline('symfony security:check --disable-exit-code --format=json --dir=' . escapeshellarg($this->appDir));
        $process->mustRun();
        $vulnerabilities = json_decode($process->getOutput(), true);
        if (!is_array($vulnerabilities)) {
            throw new Exception('Unexpected result from symfony security:check');
        }
        // Ignore vulnerabilities in composerignore.json file
        $vulnerabilities = $this->removeComposerIgnore($vulnerabilities);
        if (count($vulnerabilities) === 0) {
            $this->out->writeln('No packages have known vulnerabilities');
        } else {
            $this->displayVulnerabilities($vulnerabilities);
            $this->out->writeln('');
            if (!$this->noInteraction) {
                if (!$this->local) {
                    throw new Exception('Fix security vulnerabilities before deploying');
                }
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('Continue anyways? '))) {
                    throw new Exception('Aborting due to security vulnerability');
                }
            }
        }
    }

    private function runJsSecurityCheck()
    {
        $this->out->writeln('Running npm audit...');
        $process = $this->exec('npm audit', false);
        if ($process->getExitCode() === 0) {
            $this->out->writeln('No node modules have known vulnerabilities');
        } else {
            $this->out->writeln('');
            if (!$this->noInteraction) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('<error>Continue despite JS security vulnerabilities?</error> '))) {
                    throw new Exception('Aborting due to JS security vulnerability');
                }
            }
        }
    }

    /**
     * Runs a shell command, displaying output as it is generated.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * ($type parameter in run callback is required but not used)
     */
    private function exec($cmd, $mustRun = true, $raw = false): Process
    {
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(null);
        $run = $mustRun ? 'mustRun' : 'run';
        $process->$run(function ($type, $buffer) use ($raw) {
            if ($raw) {
                $this->out->write($buffer, false, OutputInterface::OUTPUT_RAW);
            } else {
                $this->out->write($buffer);
            }
        });
        return $process;
    }

    private function removeComposerIgnore($vulnerabilities): array
    {
        $newVulnerabilities = $vulnerabilities;
        $ignoredVulnerabilities = json_decode(file_get_contents($this->appDir . DIRECTORY_SEPARATOR . 'composerignore.json'), true);
        foreach ($vulnerabilities as $key => $vulnerability) {
            if (isset($vulnerability['advisories']) && is_array($vulnerability['advisories'])) {
                $advisories = $vulnerability['advisories'];
                foreach ($vulnerability['advisories'] as $advisoryKey => $advisory) {
                    if (!empty($advisory['link'])) {
                        if ($this->isVulnerabilityIgnored($ignoredVulnerabilities, $advisory['link'])) {
                            //Remove ignored advisories
                            unset($advisories[$advisoryKey]);
                        }
                    }
                }
                if (!empty($advisories)) {
                    $newVulnerabilities[$key]['advisories'] = $advisories;
                } else {
                    unset($newVulnerabilities[$key]);
                }
            }
        }
        return $newVulnerabilities;
    }

    private function isVulnerabilityIgnored($vulnerabilities, $link): bool
    {
        foreach ($vulnerabilities as $vulnerability) {
            if ($link == $vulnerability['link']) {
                return true;
            }
        }
        return false;
    }
}
