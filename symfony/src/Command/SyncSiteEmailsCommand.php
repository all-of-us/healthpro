<?php

namespace App\Command;

use App\Entity\Site;
use App\Service\EnvironmentService;
use App\Service\SiteSyncService;
use App\Service\GcTaskService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SyncSiteEmailsCommand extends Command
{
    private $siteSyncService;
    private $gcTaskService;
    private $environmentService;
    private $em;
    private $router;
    private $adminEmails = [];

    public function __construct(EnvironmentService $environmentService, SiteSyncService $siteSyncService, GcTaskService $gcTaskService, EntityManagerInterface $em, UrlGeneratorInterface $router)
    {
        $this->environmentService = $environmentService;
        $this->gcTaskService = $gcTaskService;
        $this->siteSyncService = $siteSyncService;
        $this->router = $router;
        $this->em = $em;
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('pmi:sitesync:emails')
            ->addOption('runLocal', null, InputOption::VALUE_OPTIONAL, 'Run the local update (skip Cloud Task Queue)', false)
            ->setDescription('Sync existing site administrator emails.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do not set site emails on staging/stable environments
        if (!$this->environmentService->isProd() && !$this->environmentService->isLocal()) {
            $output->writeln('Site email sync not allowed in this environment.');
            return 0;
        }

        // Get site list
        $sites = $this->em->getRepository(Site::class)->findBy(['status' => 1, 'deleted' => 0]);

        // Skip cloud task queue and run as batch
        if ($input->getOption('runLocal') === null || $input->getOption('runLocal')) {
            foreach ($sites as $site) {
                $output->writeln($site->getName());
                $siteAdmins = $this->siteSyncService->getSiteAdminEmails($site);
                if (count($siteAdmins) > 0) {
                    $output->writeln(' └ Setting: ' . join(', ', $siteAdmins));
                }
                $site->setEmail(join(', ', $siteAdmins));
                $this->em->persist($site);
            }
            $this->em->flush();
            $output->writeln('');
            return 0;
        }

        $queue = $this->gcTaskService->createQueue('test-task-queue');
        foreach ($sites as $site) {
            try {
                $task = $this->gcTaskService->createTask([
                    'url' => $this->router->generate('cloud_tasks_sync_site_email'),
                    'body' => http_build_query(['site_id' => $site->getId()])
                ]);
                $response = $this->gcTaskService->addTaskToQueue($queue, $task);
                $output->writeln(sprintf('Created task %s' . PHP_EOL, $response->getName()));
            } catch (\Exception $e) {
                $output->writeln($e->getMessage());
                return 1;
            }
        }

        $this->gcTaskService->close();

        return 0;
    }
}
