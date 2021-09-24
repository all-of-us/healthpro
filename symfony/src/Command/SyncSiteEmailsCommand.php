<?php

namespace App\Command;

use App\Entity\Site;
use App\Service\EnvironmentService;
use App\Service\GoogleGroupsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncSiteEmailsCommand extends Command
{
    private $googleGroupsService;
    private $environmentService;
    private $em;
    private $adminEmails = [];

    private const MEMBER_DOMAIN = '@pmi-ops.org';

    public function __construct(EnvironmentService $environmentService, GoogleGroupsService $googleGroupsService, EntityManagerInterface $em)
    {
        $this->environmentService = $environmentService;
        $this->googleGroupsService = $googleGroupsService;
        $this->em = $em;
        parent::__construct();
    }

    public function configure(): void
    {
        $this
            ->setName('pmi:sitesync:emails')
            ->setDescription('Sync existing site administrator emails.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Do not set site emails on staging/stable environments
        if (!$this->environmentService->isProd() && !$this->environmentService->isLocal()) {
            $output->writeln('Site email sync not allowed in this environment.', OutputInterface::VERBOSITY_VERBOSE);
            return 0;
        }

        // Get site list
        $sites = $this->em->getRepository(Site::class)->findBy(['status' => 1, 'deleted' => 0]);
        $progressBar = new ProgressBar($output, count($sites));
        $progressBar->start();
        foreach ($sites as $site) {
            $progressBar->advance();
            $output->writeln($site->getName(), OutputInterface::VERBOSITY_VERBOSE);
            $siteAdmins = [];
            $members = $this->googleGroupsService->getMembers($site->getSiteId() . self::MEMBER_DOMAIN, ['OWNER', 'MANAGER']);
            if (count($members) === 0) {
                $output->writeln(' └ No members set for site!', OutputInterface::VERBOSITY_VERBOSE);
                $site->setEmail(null);
                continue;
            }
            foreach ($members as $member) {
                if ($member->status === 'ACTIVE') {
                    $output->writeln(' └ User: ' . $member->email, OutputInterface::VERBOSITY_VERBOSE);
                    if (isset($this->adminEmails[$member->email])) {
                        $output->writeln('  └ ' . $this->adminEmails[$member->email] . ' (matched previous lookup)', OutputInterface::VERBOSITY_VERBOSE);
                        $siteAdmins[] = $this->adminEmails[$member->email];
                        continue;
                    }
                    $user = $this->googleGroupsService->getUser($member->email);
                    $userEmail = $user->recoveryEmail;
                    $output->writeln('  └ ' . $userEmail, OutputInterface::VERBOSITY_VERBOSE);
                    $output->writeln(json_encode($user), OutputInterface::VERBOSITY_DEBUG);
                    $this->adminEmails[$member->email] = $userEmail;
                    $siteAdmins[] = $userEmail;
                }
            }
            $statusMessage = ' └ Setting: ' . join(', ', $siteAdmins);
            $output->writeln($statusMessage, OutputInterface::VERBOSITY_VERBOSE);
            $site->setEmail(join(', ', $siteAdmins));
            $this->em->persist($site);
        }
        $progressBar->finish();
        $this->em->flush();
        $output->writeln('');
        return 0;
    }
}
