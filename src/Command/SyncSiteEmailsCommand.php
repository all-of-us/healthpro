<?php

namespace App\Command;

use App\Audit\Log;
use App\Entity\Site;
use App\Entity\SiteSync;
use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\SiteSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class SyncSiteEmailsCommand extends Command
{
    private $siteSyncService;
    private $environmentService;
    private $loggerService;
    private $normalizer;
    private $em;
    private $router;

    public function __construct(EnvironmentService $environmentService, SiteSyncService $siteSyncService, LoggerService $loggerService, NormalizerInterface $normalizer, EntityManagerInterface $em, UrlGeneratorInterface $router)
    {
        $this->environmentService = $environmentService;
        $this->siteSyncService = $siteSyncService;
        $this->loggerService = $loggerService;
        $this->normalizer = $normalizer;
        $this->router = $router;
        $this->em = $em;
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('pmi:sitesync:emails')
            ->addArgument('limit', InputArgument::OPTIONAL, 'Count of Site records to process')
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

        $limit = $input->getArgument('limit') ?? 100;

        // Get site list
        $sites = $this->em->getRepository(Site::class)->getSiteSyncQueue('adminEmail', $limit);
        if (count($sites) === 0) {
            $output->writeln('All Sites complete.');
            return 0;
        }

        foreach ($sites as $site) {
            $output->writeln($site->getName());
            $existingArray = $this->normalizer->normalize($site, null, [AbstractNormalizer::IGNORED_ATTRIBUTES => ['siteSync']]);
            $siteAdmins = $this->siteSyncService->getSiteAdminEmails($site);
            if ($site->getEmail() !== join(', ', $siteAdmins)) {
                $output->writeln(' └ Old: ' . $site->getEmail());
                $output->writeln(' └ New: ' . join(', ', $siteAdmins));
                $site->setEmail(join(', ', $siteAdmins));
                $this->em->persist($site);
                $siteDataArray = $this->normalizer->normalize($site, null, [AbstractNormalizer::IGNORED_ATTRIBUTES => ['siteSync']]);
                $this->loggerService->log(Log::SITE_EDIT, [
                    'id' => $site->getId(),
                    'old' => $existingArray,
                    'new' => $siteDataArray
                ]);
            }
            $siteSync = $site->getSiteSync();
            if (!$siteSync) {
                $siteSync = new SiteSync();
                $siteSync->setSite($site);
            }
            $siteSync->setAdminEmailsAt(new \DateTime());
            $this->em->persist($siteSync);
        }
        $this->em->flush();
        $output->writeln('');
        return 0;
    }
}
