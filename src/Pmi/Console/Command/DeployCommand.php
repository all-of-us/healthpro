<?php
namespace Pmi\Console\Command;

use Pmi\Application\AbstractApplication;
use SensioLabs\Security\SecurityChecker;
use SensioLabs\Security\Exception\HttpException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Yaml\Parser;
use Exception;

class DeployCommand extends Command {

    /** GAE application IDs for production. */
    private static $PROD_APP_IDS = [
        'healthpro-prod'
    ];

    /** GAE application IDs for security testing and/or training. */
    private static $STABLE_APP_IDS = [
        'pmi-hpo-test'
    ];

    /** GAE application IDs for staging. */
    private static $STAGING_APP_IDS = [
        'pmi-hpo-staging', // dry run environment
        'healthpro-beta', // beta environment
        'healthpro-staging' // staging environment
    ];

    /** Create release tag when deploying these application IDs. */
    private static $TAG_APP_IDS = [
        'healthpro-prod',
        'pmi-hpo-test'
    ];

    /** Don't require `login: admin` for these application IDs. */
    private static $SKIP_ADMIN_APP_IDS = [
        'pmi-hpo-dev' // this is behind a WAF so we don't want GAE login
    ];

    /** Apply enhanced instance class and scaling for these application IDs. */
    private static $SCALE_APP_IDS = [
        'healthpro-prod'
    ];

    /**#@+ Config settings set by execute(). */
    private $appDir;
    private $appId;
    private $local;
    private $port;
    private $in;
    private $out;
    private $release;

    protected function configure()
    {
        $appDir = realpath(__DIR__ . '/../../../..');
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
                'phpCgiPath',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Path to the php-cgi binary'
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
                'local-php',
                null,
                InputOption::VALUE_NONE,
                'Used in conjuction with --local; Will use PHP\'s built-in development server if set'
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
            ->addOption(
                'datastoreDir',
                null,
                InputOption::VALUE_OPTIONAL,
                'Specify local Datastore directory (use with --local option)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->in = $input;
        $this->out = $output;
        $this->out->setFormatter(new OutputFormatter(true)); // color output
        $this->appId = $input->getOption('appId');
        $this->local = (boolean) $input->getOption('local');
        $this->port = (integer) $input->getOption('port');
        $this->release = $input->getOption('release');
        $this->phpCgiPath = $input->getOption('phpCgiPath');

        if (!$this->appId && !$this->local) {
            throw new InvalidOptionException('Please specify --appId (-i) or --local');
        }

        $this->appDir = preg_replace('%[\\/]*$%', '', $input->getOption('appDir'));
        if (!file_exists($this->appDir) || !is_dir("$this->appDir")) {
            throw new InvalidOptionException("Bad application directory: {$this->appDir}");
        }
        // ensure shell commands (like `git`) are executed from inside repo
        chdir($this->appDir);

        // deal with GAE conflict with libxml_disable_entity_loader()
        $this->patchLibxmlDisable();

        // generate config files
        $this->generateAppConfig();
        $this->generatePhpConfig();
        $this->generateCronConfig();

        // If not local, compile assets. Run ./bin/gulp when developing locally.
        if (!$this->local) {
            // ensure that we are up-to-date with the latest NPM dependencies
            $output->writeln('');
            $output->writeln("Checking NPM dependencies...");
            $this->exec("npm install --no-audit"); // npm audit will be run below in the runJsSecurityCheck method

            // compile (concat/minify/copy) assets
            $output->writeln('');
            $output->writeln("Compiling assets...");
            $this->exec("{$this->appDir}/bin/gulp compile");

            // warmup twig cache
            $output->writeln('');
            $output->writeln('Warming up Twig cache...');
            $this->exec("{$this->appDir}/bin/phpcli bin/console pmi:twig");
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
            if ($input->getOption('local-php')) {
                $cmd = "php -S localhost:{$this->port} -t web/ web/local-router.php";
            } else {
                $cmd = "dev_appserver.py -A pmi-hpo-dev --port={$this->port}";
                if ($this->phpCgiPath) {
                    $cmd .= " --php_executable_path {$this->phpCgiPath}";
                }
                if ($dsDir = $input->getOption('datastoreDir')) {
                    $dsDir = rtrim($dsDir, '/');
                    $dsDir .= '/datastore.db';
                    $cmd .= " --datastore_path={$dsDir}";
                }
                $cmd .= " {$this->appDir}/app.yaml";
            }
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
        $envFavicon = "{$this->appDir}/web/assets/favicon/favicon_" . $this->determineEnv() . ".ico";
        if (file_exists($envFavicon)) {
            copy($envFavicon, $favicon);
        }

        $question = $this->getHelper('question');
        $this->exec("git status"); // display git status
        $gitStatus = new ConfirmationQuestion("<comment>Does git status look good? (y/n)</comment> ",
            false, '/^(y|yes)$/');
        $destinationText = $this->isProd() ? ' <error>TO PRODUCTION</error>!' : ' to ' . $this->determineEnv();
        $reallyDeploy = new ConfirmationQuestion("<question>Do you REALLY want to deploy{$destinationText}? (y/n)</question> ",
            false, '/^(y|yes)$/');
        if ($this->local || ($question->ask($input, $output, $gitStatus) && $question->ask($input, $output, $reallyDeploy))) {
            $this->exec($cmd, true, true);
            if ($this->isTaggable()) {
                $this->tagRelease();
            }
            $output->writeln('');
            $output->writeln('All done 👍'); // emoji :thumbsup:
            // taggable implies we are tracking/auditing
            if ($this->isTaggable()) {
                $output->writeln('');
                $output->writeln('<error>Remember to attach deploy output to Jira ticket!</error>');
            }
        }
        else {
            $output->writeln('');
            $output->writeln('Fine then, be that way 😾'); // emoji :pouting_cat:
        }
    }

    /** Determines the environment we are deploying to. */
    public function determineEnv() {
        if ($this->local) {
            return AbstractApplication::ENV_LOCAL;
        } elseif ($this->isDev()) {
            return AbstractApplication::ENV_DEV;
        } elseif ($this->isStable()) {
            return AbstractApplication::ENV_STABLE;
        } elseif ($this->isStaging()) {
            return AbstractApplication::ENV_STAGING;
        } elseif ($this->isProd()) {
            return AbstractApplication::ENV_PROD;
        } else {
            throw new \Exception('Unable to determine environment!');
        }
    }

    private function isDev()
    {
        return !$this->local && !$this->isStable() && !$this->isStaging() && !$this->isProd();
    }

    private function isStable()
    {
        return !$this->local && in_array($this->appId, self::$STABLE_APP_IDS);
    }
    
    private function isStaging()
    {
        return !$this->local && in_array($this->appId, self::$STAGING_APP_IDS);
    }

    private function isProd()
    {
        return !$this->local && in_array($this->appId, self::$PROD_APP_IDS);
    }

    private function isTaggable()
    {
        return !$this->local && in_array($this->appId, self::$TAG_APP_IDS);
    }

    /** Adds and pushes a release tag to git. */
    private function tagRelease()
    {
        $tag = $tag = "REL_{$this->appId}_{$this->release}";
        $this->out->writeln('');
        $this->out->writeln("<info>Setting release tag to {$tag}...</info>");
        $this->exec("git tag $tag");
        $this->out->writeln("<info>Pushing tag...</info>");
        $this->exec("git push origin $tag");
    }

    /**
     * GAE disables the libxml_disable_entity_loader() function for security,
     * but several Symfony files try to call it (also for security). This results
     * in a PHP warning on every page that will always be visible to app admins
     * due to this: http://stackoverflow.com/a/23026196/1402028
     * Rather than override GAE by enabling this insecure function, comment out
     * the calls.
     */
    private function patchLibxmlDisable()
    {
        $files = [
            'symfony/translation/Loader/XliffFileLoader.php',
            'symfony/config/Util/XmlUtils.php'
        ];
        foreach ($files as $file) {
            $filename = "{$this->appDir}/vendor/{$file}";
            $contents = file_get_contents($filename);

            // Added the negation for the new line character because if the previous line is blank,
            // the match would include the leading new line and place the comment on the previous line.
            $patched = preg_replace('#^[^/\n][^/].*libxml_disable_entity_loader\(.*$#m', '//$0', $contents);

            if ($contents !== $patched) {
                file_put_contents($filename, $patched);
            }
        }
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
        
        // do this prior to excluding crons so developers are notified about
        // missing backups well in advance of to deploying to production
        $this->checkCronBackups($config);
        
        // adjust crons depending on environment
        $crons = [];
        foreach ($config['cron'] as $c) {
            if (stristr($c['url'], '/ping-test') !== false && $this->isProd()) {
                continue;
            } elseif (stristr($c['url'], '/_ah/datastore_admin/backup.create') !== false) {
                // enable DS backup only in production and test sites
                if (!$this->isProd() && !$this->isStable() && !$this->isStaging()) {
                    continue;
                } else {
                    $c['url'] = str_replace('{BUCKET_PREFIX}', $this->appId, $c['url']);
                }
            }
            $crons[] = $c;
        }
        $config['cron'] = $crons;
        
        $dumper = new Dumper();
        file_put_contents($configFile, count($crons) ? $dumper->dump($config, PHP_INT_MAX) : 'cron:');
    }

    private function checkCronBackups($config)
    {
        $cronKinds = [];
        foreach ($config['cron'] as $c) {
            if (stripos($c['url'], '/_ah/datastore_admin/backup.create') === 0) {
                if (strlen($c['url']) > 2000) {
                    throw new \Exception("URL character limit exceeded: {$c['url']}");
                }
                if (preg_match_all('/kind=(\w+)/', $c['url'], $m) > 0) {
                    foreach ($m[1] as $kind) {
                        $cronKinds[$kind] = $kind;
                    }
                }
            }
        }
        
        $pmiKinds = [];
        $files = glob("{$this->appDir}/src/Pmi/Entities/*.php");
        if (count($files) === 0) {
            throw new \Exception("No Datastore entity classes were found!");
        }
        foreach ($files as $file) {
            $entity = basename($file, '.php');
            $pmiKinds[$entity] = $entity;
        }

        foreach ($cronKinds as $kind) {
            if (empty($pmiKinds[$kind])) {
                throw new \Exception("No entity exists for datastore backup of kind {$kind}!");
            }
        }
    }

    /** Tell all handlers to require Google login. */
    private function requireGoogleLogin(&$config)
    {
        foreach (array_keys($config['handlers']) as $idx) {
            $route = trim($config['handlers'][$idx]['url']);
            // so we can display a user-friendly message when the session times
            // out, rather than the Google account selector
            if ($route === '/timeout') {
                continue;
            }
            $config['handlers'][$idx]['login'] = 'required';
        }
    }

    /** Check all handlers for security concerns. */
    private function checkHandlerSecurity($config)
    {
        $this->out->writeln("Checking URL handler security...");
        foreach ($config['handlers'] as $handler) {
            if (empty($handler['secure']) || $handler['secure'] !== 'always') {
                throw new \Exception("Handler URL '{$handler['url']}' does not force SSL!");
            }
        }
        $this->out->writeln('... all ' . count($config['handlers']) . " handlers are secure.");
    }

    /** Sets the app's environment variables. */
    private function configureEnv(&$config)
    {
        $config['env_variables']['PMI_ENV'] = $this->determineEnv();
        $config['env_variables']['PMI_RELEASE'] = $this->release;
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
            $dependencyFullName = $dependency.' ('.$issues['version'].')';
            $this->out->writeln('<info>'.$dependencyFullName."\n".str_repeat('-', strlen($dependencyFullName))."</>\n");
            foreach ($issues['advisories'] as $details) {
                $this->out->write(' * ');
                if ($details['cve']) {
                    $this->out->write('<comment>'.$details['cve'].': </comment>');
                }
                $this->out->writeln($details['title']);
                if ('' !== $details['link']) {
                    $this->out->writeln('   '.$details['link']);
                }
                $this->out->writeln('');
            }
        }
    }

    private function runSecurityCheck()
    {
        $composerLockFile = $this->appDir . DIRECTORY_SEPARATOR . 'composer.lock';
        $this->out->writeln("Running SensioLabs Security Checker...");
        $checker = new SecurityChecker();
        $helper = $this->getHelper('question');
        try {
            $vulnerabilities = json_decode((string)$checker->check($composerLockFile), true);
        } catch (HttpException $e) {
            $this->out->writeln('<error>' . $e->getMessage() . '</error>');
            if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('Continue anyways? '))) {
                throw new \Exception('Aborting due to SensioLabs Security Checker network error');
            }
        }
        // Ignore vulnerabilities mentioned in sensioignore file
        $vulnerabilities = $this->removeSensioIgnoredVulnerabilities($vulnerabilities);
        if (count($vulnerabilities) === 0) {
            $this->out->writeln('No packages have known vulnerabilities');
        } else {
            $this->displayVulnerabilities($vulnerabilities);
            if (!$this->local) {
                throw new \Exception('Fix security vulnerabilities before deploying');
            } else {
                if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('Continue anyways? '))) {
                    throw new \Exception('Aborting due to security vulnerability');
                }
            }
        }
    }

    private function runJsSecurityCheck()
    {
        $this->out->writeln("Running npm audit...");
        $process = $this->exec('npm audit', false);
        if ($process->getExitCode() === 0) {
            $this->out->writeln('No node modules have known vulnerabilities');
        } else {
            $this->out->writeln('');
            $helper = $this->getHelper('question');
            if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('<error>Continue despite JS security vulnerabilities?</error> '))) {
                throw new \Exception('Aborting due to JS security vulnerability');
            }
        }
    }

    /**
     * Runs a shell command, displaying output as it is generated.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * ($type parameter in run callback is required but not used)
     */
    private function exec($cmd, $mustRun = true, $raw = false)
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $run = $mustRun ? 'mustRun' : 'run';
        $process->$run(function($type, $buffer) use ($raw) {
            if ($raw) {
                $this->out->write($buffer, false, OutputInterface::OUTPUT_RAW);
            } else {
                $this->out->write($buffer);
            }
        });
        return $process;
    }


    private function removeSensioIgnoredVulnerabilities($vulnerabilities)
    {
        $newVulnerabilities = $vulnerabilities;
        $sensioIgnoredVulnerabilities = json_decode(file_get_contents($this->appDir . DIRECTORY_SEPARATOR . 'sensioignore.json'), true);
        foreach ($vulnerabilities as $key => $vulnerability) {
            if (!empty($vulnerability['advisories'])) {
                $advisories = $vulnerability['advisories'];
                foreach($vulnerability['advisories'] as $advisoryKey => $advisory) {
                    if (!empty($advisory['link'])) {
                        if ($this->isVulnerabilityIgnored($sensioIgnoredVulnerabilities, $advisory['link'])) {
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

    private function isVulnerabilityIgnored($vulnerabilities, $link)
    {
        foreach ($vulnerabilities as $vulnerability) {
            if ($link == $vulnerability['link']) {
                return true;
            }
        }
        return false;
    }
}
