<?php

namespace App\Command;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

class RotateCloudSqlPasswordCommand extends Command
{
    private const PASSWORD_LENGTH = 24;
    protected static $defaultName = 'app:rotate-cloudsql-password';

    private SymfonyStyle $io;
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params)
    {
        $this->params = $params;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Rotates MySQL user passwords for a Cloud SQL instance and updates Secret Manager.')
            ->addArgument('instance', InputArgument::REQUIRED, 'The Cloud SQL instance name.')
            ->addOption('project', null, InputOption::VALUE_OPTIONAL, 'The Google Cloud project ID. If not provided, the instance name is used.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Perform a dry run without making any changes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $instance = $input->getArgument('instance');
        $project = $input->getOption('project') ?? $instance;
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $this->io->note('-- DRY RUN MODE --');
        }

        if (!$this->io->confirm("Are you sure you want to rotate passwords for instance \"{$instance}\" in project \"{$project}\"?", false)) {
            $this->io->warning('Command aborted.');
            return Command::SUCCESS;
        }

        try {
            $users = $this->params->get('cloudsql_users');
            // 1. Generate new passwords
            $this->io->writeln('Generating new passwords...');
            $newPasswords = [];
            foreach ($users as $user) {
                $newPasswords[$user] = $this->generatePassword();
            }

            // 2. Rotate Cloud SQL passwords
            $this->io->writeln("Rotating Cloud SQL passwords for instance \"{$instance}\"...");
            foreach ($newPasswords as $user => $password) {
                $this->io->writeln("Updating password for user \"{$user}\"...");
                $command = sprintf(
                    'gcloud sql users set-password %s --host=%% --instance=%s --project=%s --password=\'%s\' --quiet',
                    $user,
                    $instance,
                    $project,
                    '**********'
                );
                if ($dryRun) {
                    $this->io->writeln("[DRY RUN] Would execute: {$command}");
                } else {
                    $this->exec(
                        sprintf(
                            'gcloud sql users set-password %s --host=%% --instance=%s --project=%s --password=\'%s\' --quiet',
                            $user,
                            $instance,
                            $project,
                            $password
                        )
                    );
                }
            }
            if (!$dryRun) {
                $this->io->success('Successfully rotated Cloud SQL passwords.');
            }

            // 3. Update Secret Manager
            $this->io->writeln('Updating secrets in Secret Manager...');

            // a. Update 'credentials' secret
            $this->io->writeln('Updating "credentials" secret...');
            $credentialsVersionOutput = $this->updateCredentialsSecret($newPasswords[$this->params->get('cloudsql_web_user')], $project, $dryRun);
            if (!$dryRun) {
                $this->io->success('Successfully updated "credentials" secret.');
            }

            // b. Update 'cloud_sql_credentials' secret
            $this->io->writeln('Updating "cloud_sql_credentials" secret...');
            $cloudSqlCredentialsVersionOutput = $this->updateCloudSqlCredentialsSecret($newPasswords, $project, $dryRun);
            if (!$dryRun) {
                $this->io->success('Successfully updated "cloud_sql_credentials" secret.');
            }

            $this->io->writeln('');
            if ($dryRun) {
                $this->io->success('Dry run complete. No changes were made.');
            } else {
                $this->io->success('Password rotation complete!');
                $this->io->writeln("Project: {$project}");
                $this->io->writeln("Instance: {$instance}");
                $this->io->writeln('Users updated: ' . implode(', ', $users));
                $this->io->writeln("New credentials secret version info: {$credentialsVersionOutput}");
                $this->io->writeln("New cloud_sql_credentials secret version info: {$cloudSqlCredentialsVersionOutput}");
            }
        } catch (Exception $e) {
            $this->io->error("An error occurred: {$e->getMessage()}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function generatePassword(): string
    {
        $charSets = [
            'lowercase' => 'abcdefghijklmnopqrstuvwxyz',
            'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numbers' => '0123456789',
            'symbols' => '!@#$%^&*()_+-='
        ];

        $passwordChars = [];
        $allChars = '';

        // Ensure at least one character from each set
        foreach ($charSets as $set) {
            $passwordChars[] = $set[random_int(0, strlen($set) - 1)];
            $allChars .= $set;
        }

        // Fill the rest of the password with random characters from all sets
        $remainingLength = self::PASSWORD_LENGTH - count($charSets);
        for ($i = 0; $i < $remainingLength; $i++) {
            $passwordChars[] = $allChars[random_int(0, strlen($allChars) - 1)];
        }

        // Shuffle the password to randomize character positions
        shuffle($passwordChars);

        return implode('', $passwordChars);
    }

    private function updateCredentialsSecret(string $newPassword, string $project, bool $dryRun): string
    {
        // Get latest enabled version number
        $command = sprintf(
            "gcloud secrets versions list credentials --filter='state=ENABLED' --sort-by='~name' --limit=1 --format='value(name)' --project=%s",
            $project
        );
        $latestVersionProcess = Process::fromShellCommandline($command);
        $latestVersionProcess->mustRun();
        $previousVersion = trim($latestVersionProcess->getOutput());

        // Get latest version of 'credentials' secret
        $process = Process::fromShellCommandline(sprintf(
            'gcloud secrets versions access latest --secret=credentials --project=%s',
            $project
        ));
        $process->mustRun();
        $credentialsJson = $process->getOutput();
        $credentials = json_decode($credentialsJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse "credentials" secret JSON.');
        }

        // Update password
        $credentials['mysql_password'] = $newPassword;
        $updatedCredentialsJson = json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $command = sprintf(
            'gcloud secrets versions add credentials --data-file=- --project=%s',
            $project
        );

        if ($dryRun) {
            $this->io->writeln("[DRY RUN] Would execute: {$command}");
            if ($previousVersion) {
                $this->io->writeln(sprintf('[DRY RUN] Would disable previous secret version: %s for secret "credentials"', $previousVersion));
            }
            return 'dry-run-version';
        }

        // Add new version
        $process = Process::fromShellCommandline($command);
        $process->setInput($updatedCredentialsJson);
        $process->mustRun();
        $newVersionOutput = trim($process->getOutput());

        // Disable previous version
        if ($previousVersion) {
            $this->io->writeln(sprintf('Disabling previous secret version: %s for secret "credentials"', $previousVersion));
            $this->exec(sprintf(
                'gcloud secrets versions disable %s --secret=credentials --project=%s --quiet',
                $previousVersion,
                $project
            ));
        }

        return $newVersionOutput;
    }

    private function updateCloudSqlCredentialsSecret(array $passwords, string $project, bool $dryRun): string
    {
        // Get latest enabled version number
        $command = sprintf(
            "gcloud secrets versions list cloud_sql_credentials --filter='state=ENABLED' --sort-by='~name' --limit=1 --format='value(name)' --project=%s",
            $project
        );
        $latestVersionProcess = Process::fromShellCommandline($command);
        $latestVersionProcess->mustRun();
        $previousVersion = trim($latestVersionProcess->getOutput());

        $passwordsJson = json_encode($passwords, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $command = sprintf(
            'gcloud secrets versions add cloud_sql_credentials --data-file=- --project=%s',
            $project
        );

        if ($dryRun) {
            $this->io->writeln("[DRY RUN] Would execute: {$command}");
            if ($previousVersion) {
                $this->io->writeln(sprintf('[DRY RUN] Would disable previous secret version: %s for secret "cloud_sql_credentials"', $previousVersion));
            }
            return 'dry-run-version';
        }

        // Add new version
        $process = Process::fromShellCommandline($command);
        $process->setInput($passwordsJson);
        $process->mustRun();
        $newVersionOutput = trim($process->getOutput());

        // Disable previous version
        if ($previousVersion) {
            $this->io->writeln(sprintf('Disabling previous secret version: %s for secret "cloud_sql_credentials"', $previousVersion));
            $this->exec(sprintf(
                'gcloud secrets versions disable %s --secret=cloud_sql_credentials --project=%s --quiet',
                $previousVersion,
                $project
            ));
        }

        return $newVersionOutput;
    }

    private function exec(string $cmd): void
    {
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(null);
        $process->mustRun(function ($type, $buffer) {
            $this->io->write($buffer);
        });
    }
}
