<?php
namespace Pmi\Console\Command;

use GeoIp2\Database\Reader;
use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Dumper;

class IpWhitelistCommand extends Command
{
    private $appDir;
    private $output;
    
    protected function configure()
    {
        $this->appDir = realpath(__DIR__ . '/../../../..');
        $this
            ->setName('pmi:ipwhitelist')
            ->setDescription('Generates a list of whitelisted IPs')
        ;
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $output->setFormatter(new OutputFormatter(true)); // color output
        
        $output->writeln("Downloading GeoIP2 country database...");
        $db = file_get_contents('http://geolite.maxmind.com/download/geoip/database/GeoLite2-Country.mmdb.gz');
        $dbFile = "{$this->appDir}/bin/GeoLite2-Country.mmdb";
        file_put_contents($dbFile, gzdecode($db));
        $output->writeln("... database downloaded to {$dbFile}");
        $output->writeln('');
        
        $output->writeln("Querying IPs...");
        $client = new Client([
            'base_uri' => 'https://my.incapsula.com'
        ]);
        $response = $client->request('POST', '/api/integration/v1/ips', [
            'query' => ['content' => 'json']
        ]);
        $networks = json_decode((string) $response->getBody());
        $output->writeln('... IPv4 addrs: ' . count($networks->ipRanges) . ', IPv6 addrs: ' . count($networks->ipv6Ranges));
        $output->writeln('');
        
        $output->writeln("Checking country codes...");
        $reader = new Reader($dbFile);
        $config = ['whitelist' => []];
        foreach (array_merge($networks->ipRanges, $networks->ipv6Ranges) as $network) {
            $process = $this->exec("{$this->appDir}/bin/network2ip " . escapeshellarg($network), true, true);
            $ip = trim($process->getOutput());
            $record = $reader->country($ip);
            $descr = "... {$network}... {$ip}... {$record->country->isoCode}";
            if ($record->country->isoCode === 'US') {
                $config['whitelist'][] = $network;
                $output->writeln("<info>$descr</info>");
            } else {
                $output->writeln("<error>$descr</error>");
            }
        }
        $output->writeln('');
        $config['whitelist'][] = '0.0.0.0/8'; // whitelist internal 0.* block
        $configFile = "{$this->appDir}/ip_whitelist.yml.dist";
        $output->write("Writing $configFile...");
        $dumper = new Dumper();
        file_put_contents($configFile, $dumper->dump($config, PHP_INT_MAX));
        $output->writeln(' done!');
        $output->writeln('');
        
        
        $dosFile = "{$this->appDir}/dos.yaml.dist";
        $output->write("Writing $dosFile...");
        $csv = implode(',', $config['whitelist']);
        $process = $this->exec("{$this->appDir}/bin/invertNetworks $csv", true, true);
        $dosCsv = trim($process->getOutput());
        $subnets = explode(',', $dosCsv);
        $dosConfig = ['blacklist' => []];
        foreach ($subnets as $subnet) {
            $dosConfig['blacklist'][] = ['subnet' => $subnet];
        }
        $dumper = new Dumper();
        file_put_contents($dosFile, $dumper->dump($dosConfig, PHP_INT_MAX));
        $output->writeln(' done!');
    }
    
    /** Runs a shell command, displaying output as it is generated. */
    private function exec($cmd, $mustRun = true, $silent = false)
    {
        $process = new Process($cmd);
        $process->setTimeout(null);
        $run = $mustRun ? 'mustRun' : 'run';
        $process->$run(function($type, $buffer) use ($silent) {
            if (!$silent) {
                $this->output->write($buffer);
            }
        });
        return $process;
    }
}
