<?php

namespace App\Command;

use GeoIp2\Database\Reader;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Process\Process;

class FirewallCommand extends Command
{
    private $appDir;
    private $output;

    protected function configure(): void
    {
        $this->appDir = realpath(__DIR__ . '/../../..');
        $this
            ->setName('pmi:firewall')
            ->setDescription('Generate rules for the GAE firewall');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $output->setFormatter(new OutputFormatter(true)); // color output
        $dbFile = "{$this->appDir}/symfony/bin/GeoLite2-Country.mmdb";
        if (!file_exists($dbFile)) {
            $output->writeln("Downloading GeoIP2 country database...");
            $db = file_get_contents('https://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.mmdb.gz');
            file_put_contents($dbFile, gzdecode($db));
            $output->writeln("... database downloaded to {$dbFile}");
            $output->writeln('');
        }
        $output->writeln("Querying IPs...");
        $client = new Client([
            'base_uri' => 'https://my.incapsula.com'
        ]);
        $response = $client->request('POST', '/api/integration/v1/ips', [
            'query' => ['content' => 'json']
        ]);
        $networks = json_decode((string)$response->getBody());
        $output->writeln('... IPv4 addrs: ' . count($networks->ipRanges) . ', IPv6 addrs: ' . count($networks->ipv6Ranges));
        $output->writeln('');

        $output->writeln("Checking country codes...");
        $reader = new Reader($dbFile);
        $rules = [
            [100, 'ALLOW', '0.0.0.0/8', 'Internal App Engine requests']
        ];
        $priority = 1000;
        foreach (array_merge($networks->ipRanges, $networks->ipv6Ranges) as $network) {
            $process = $this->exec("{$this->appDir}/bin/network2ip " . escapeshellarg($network), true, true);
            $ip = trim($process->getOutput());
            $record = $reader->country($ip);
            $descr = "... {$network}... {$ip}... {$record->country->isoCode}";
            if ($record->country->isoCode === 'US') {
                $rules[] = [$priority, 'ALLOW', $network, 'Incapsula'];
                $output->writeln("<info>$descr</info>");
            } else {
                $output->writeln("<error>$descr</error>");
            }
            $priority += 10;
        }
        $rules[] = [2147483647, 'DENY', '*', 'Deny all other traffic'];

        $output->writeln('');
        $table = new Table($output);
        $table
            ->setHeaders(['PRIORITY', 'ACTION', 'SOURCE_RANGE', 'DESCRIPTION'])
            ->setRows($rules);
        $table->render();

        $output->writeln('');
        $output->writeln('gcloud commands:');
        foreach ($rules as $rule) {
            $output->writeln("gcloud app firewall-rules create {$rule[0]} --action {$rule[1]} --source-range \"{$rule[2]}\" --description \"{$rule[3]}\"");
        }
        return 0;
    }

    /** Runs a shell command, displaying output as it is generated. */
    private function exec($cmd, $mustRun = true, $silent = false): Process
    {
        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(null);
        $run = $mustRun ? 'mustRun' : 'run';
        $process->$run(function ($type, $buffer) use ($silent) {
            if (!$silent) {
                $this->output->write($buffer);
            }
        });
        return $process;
    }
}
