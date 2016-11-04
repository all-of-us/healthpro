<?php
namespace Pmi\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;
use Pmi\Application\HpoApplication;

class TwigCacheWarmerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('pmi:twig')
            ->setDescription('PMI warm twig cache')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $appDir = realpath(__DIR__ . '/../../../..');
        $viewDir = $appDir . '/views';
        $cacheDir = $appDir . '/cache';

        if (is_dir($cacheDir . '/twig')) {
            $process = new Process('rm -rf ' . $cacheDir . '/twig');
            $process->run();
        }

        putenv('PMI_ENV=' . HpoApplication::ENV_LOCAL);
        $app = new HpoApplication([
            'templatesDirectory' => $viewDir,
            'cacheDirectory' => $cacheDir,
            'twigCacheHandler' => 'file',
            'isUnitTest' => true
        ]);
        $app->setup();

        $finder = new Finder();
        foreach ($finder->files()->in($viewDir) as $file) {
            $output->writeLn($file->getRelativePathname());
            $app['twig']->loadTemplate($file->getRelativePathname());
        }

        // Load form template
        $output->writeLn('bootstrap_3_layout.html.twig');
        $app['twig']->loadTemplate('bootstrap_3_layout.html.twig');

        $output->writeLn('Done');
    }
}
