<?php
namespace Pmi\Console\Command;

use Pmi\Application\AbstractApplication;
use SensioLabs\Security\SecurityChecker;
use SensioLabs\Security\Formatters\SimpleFormatter;
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
        'pmi-hpo'
    ];
    
    /** GAE application IDs for user/security testing. */
    private static $TEST_APP_IDS = [
        'pmi-hpo-staging',
        'pmi-hpo-test'
    ];

    /** Restrict access by IP using dos.yaml */
    private static $IPRESTRICT_APP_IDS = [
        'pmi-hpo',
        'pmi-hpo-test'
    ];

    /** Create release tag when deploying these application IDs. */
    private static $TAG_APP_IDS = [
        'pmi-hpo',
        'pmi-hpo-test'
    ];

    /** Don't require `login: admin` for these application IDs. */
    private static $SKIP_ADMIN_APP_IDS = [
        'pmi-hpo-dev' // this is behind a WAF so we don't want GAE login
    ];

    /** Apply enhanced instance class and scaling for these application IDs. */
    private static $SCALE_APP_IDS = [
        'pmi-hpo'
    ];

    /**#@+ Config settings set by execute(). */
    private $sdkDir;
    private $appDir;
    private $appId;
    private $index;
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
                'sdkDir',
                's',
                InputOption::VALUE_OPTIONAL,
                'Path to the GAE SDK',
                '/usr/local/google_appengine'
            )
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
                'index',
                'x',
                InputOption::VALUE_NONE,
                'Deploy only the indexing defined in index.yaml'
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
        $this->index = (boolean) $input->getOption('index');
        $this->local = (boolean) $input->getOption('local');
        $this->port = (integer) $input->getOption('port');
        $this->release = $input->getOption('release');
        $this->phpCgiPath = $input->getOption('phpCgiPath');

        if (!$this->appId && !$this->local) {
            throw new InvalidOptionException('Please specify --appId (-i) or --local');
        }
        $this->sdkDir = preg_replace('%[\\/]*$%', '', $input->getOption('sdkDir'));
        $deploy = $this->sdkDir . DIRECTORY_SEPARATOR .
            ($this->local ? 'dev_appserver.py' : 'appcfg.py');
        if (!file_exists($deploy)) {
            throw new InvalidOptionException("$deploy does not exist!");
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
        $this->generateIpWhitelistConfig();
        $this->generatePhpConfig();
        $this->generateCronConfig();

        $this->runSecurityCheck();

        // If not local, compile assets. Run ./bin/gulp when developing locally.
        if (!$this->local && !$this->index) {
            // ensure that we are up-to-date with the latest NPM dependencies
            $output->writeln('');
            $output->writeln("Checking NPM dependencies...");
            $this->exec("{$this->appDir}/bin/npm install");

            // ensure that we are up-to-date with the latest Bower dependencies
            $output->writeln('');
            $output->writeln("Checking Bower dependencies...");
            $this->exec("{$this->appDir}/bin/bower install");

            // compile (concat/minify/copy) assets
            $output->writeln('');
            $output->writeln("Compiling assets...");
            $this->exec("{$this->appDir}/bin/gulp compile");

            // warmup twig cache
            $output->writeln('');
            $output->writeln('Warming up Twig cache...');
            $command = $this->getApplication()->find('pmi:twig');
            $twigInput = new ArrayInput(['command' => 'pmi:twig']);
            $command->run($twigInput, $output);
        }

        // unit tests should pass before deploying to testers or production
        if ($this->isTest() || $this->isProd()) {
            $this->runUnitTests();
        }

        if ($this->local) {
            $cmd = "{$deploy} --port={$this->port} --skip_sdk_update_check=yes";
            if ($this->phpCgiPath) {
                $cmd .= " --php_executable_path {$this->phpCgiPath}";
            }
            if ($dsDir = $input->getOption('datastoreDir')) {
                $dsDir = rtrim($dsDir, '/');
                $dsDir .= '/datastore.db';
                $cmd .= " --datastore_path={$dsDir}";
            }
            $cmd .= " {$this->appDir}";
        } else {
            $method = $this->index ? 'update_indexes' : 'update';
            $cmd = "{$deploy} -A {$this->appId} {$method} {$this->appDir}";
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
        $prodYell = $this->isProd() ? ' <error>TO PRODUCTION</error>!' : '';
        $reallyDeploy = new ConfirmationQuestion("<question>Do you REALLY want to deploy{$prodYell}? (y/n)</question> ",
            false, '/^(y|yes)$/');
        if ($this->local || $question->ask($input, $output, $reallyDeploy)) {
            $this->exec($cmd);
            if ($this->isTaggable() && !$this->index) {
                $this->tagRelease();
            }
            $output->writeln('');
            $output->writeln('All done 👍'); // emoji :thumbsup:
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
        } elseif ($this->isTest()) {
            return AbstractApplication::ENV_TEST;
        } elseif ($this->isProd()) {
            return AbstractApplication::ENV_PROD;
        } else {
            throw new \Exception('Unable to determine environment!');
        }
    }

    private function isDev()
    {
        return !$this->local && !$this->isTest() && !$this->isProd();
    }
    
    private function isTest()
    {
        return !$this->local && in_array($this->appId, self::$TEST_APP_IDS);
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

        // require admin login for developer GAE sites
        if ($this->isDev() && !in_array($this->appId, self::$SKIP_ADMIN_APP_IDS)) {
            $this->requireAdminLogin($config);
        }

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
    
    /** Generate IP whitelisting config. */
    private function generateIpWhitelistConfig()
    {
        $dosFile = $this->appDir . DIRECTORY_SEPARATOR . 'dos.yaml';
        $dosDistFile = "{$dosFile}.dist";
        if (!file_exists($dosDistFile)) {
            throw new Exception("Couldn't find $dosDistFile");
        }
        
        if (in_array($this->appId, self::$IPRESTRICT_APP_IDS)) {
            copy($dosDistFile, $dosFile);
        } else {
            // https://cloud.google.com/appengine/docs/php/config/dos#delete
            file_put_contents($dosFile, 'blacklist:');
        }
        
        $whitelistFile = $this->appDir . DIRECTORY_SEPARATOR . 'ip_whitelist.yml';
        $whitelistDistFile = "{$whitelistFile}.dist";
        if (!file_exists($whitelistDistFile)) {
            throw new Exception("Couldn't find $whitelistDistFile");
        }
        
        if (in_array($this->appId, self::$IPRESTRICT_APP_IDS)) {
            copy($whitelistDistFile, $whitelistFile);
        } else {
            // https://cloud.google.com/appengine/docs/php/config/dos#delete
            file_put_contents($whitelistFile, 'whitelist:');
        }
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
                $line = 'display_errors = ' . (($this->isProd() || $this->isTest()) ? 0 : 1);
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
                if (!$this->isProd() && !$this->isTest()) {
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
        
        foreach ($pmiKinds as $kind) {
            if (empty($cronKinds[$kind]) && $kind !== 'Session') {
                throw new \Exception("Datastore kind {$kind} is not being backed up!");
            }
        }
        
        foreach ($cronKinds as $kind) {
            if (empty($pmiKinds[$kind])) {
                throw new \Exception("No entity exists for datastore backup of kind {$kind}!");
            }
        }
    }

    /** Tell all handlers to require admin login. */
    private function requireAdminLogin(&$config)
    {
        foreach (array_keys($config['handlers']) as $idx) {
            $config['handlers'][$idx]['login'] = 'admin';
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
            } elseif ($this->isDev() && !in_array($this->appId, self::$SKIP_ADMIN_APP_IDS) && (empty($handler['login']) || $handler['login'] !== 'admin')) {
                throw new \Exception("Handler URL '{$handler['url']}' does not require login!");
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
        $config['instance_class'] = 'F2'; // 256MB, 1.2 GHz
        // improve user experience during low traffic
        $config['automatic_scaling']['min_idle_instances'] = 2;
    }

    private function runUnitTests()
    {
        $this->exec("{$this->appDir}/bin/phpunit");
    }

    private function runSecurityCheck()
    {
        $composerLockFile = $this->appDir . DIRECTORY_SEPARATOR . 'composer.lock';
        $this->out->writeln("Running SensioLabs Security Checker...");
        $checker = new SecurityChecker();
        $vulnerabilities = $checker->check($composerLockFile);
        if (count($vulnerabilities) === 0) {
            $this->out->writeln('No packages have known vulnerabilities');
        } else {
            $formatter = new SimpleFormatter($this->getHelper('formatter'));
            $formatter->displayResults($this->out, $composerLockFile, $vulnerabilities);
            if (!$this->local && !$this->index) {
                throw new \Exception('Fix security vulnerablities before deploying');
            } else {
                $helper = $this->getHelper('question');
                if (!$helper->ask($this->in, $this->out, new ConfirmationQuestion('Continue anyways? '))) {
                    throw new \Exception('Aborting due to security vulnerability');
                }
            }
        }
    }

    /** Runs a shell command, displaying output as it is generated. */
    private function exec($cmd, $mustRun = true)
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $run = $mustRun ? 'mustRun' : 'run';
        $process->$run(function($type, $buffer) {
            echo $buffer;
        });
        return $process;
    }
}
