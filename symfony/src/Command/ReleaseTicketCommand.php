<?php

namespace App\Command;

use App\Service\JiraService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment as Templating;

class ReleaseTicketCommand extends Command
{
    private $jira;
    private $io;
    private $templating;

    public function __construct(JiraService $jira, Templating $templating)
    {
        $this->jira = $jira;
        $this->templating = $templating;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('pmi:jira')
            ->setDescription('Create JIRA release ticket');
    }

    private function selectVersion(array $versions): ?string
    {
        $defaultVersion = null;
        if (isset($versions[0]) && !$versions[0]->released) {
            $defaultVersion = $versions[0]->name;
        }
        $tableHeaders = ['Version', 'Released?', 'Release Date', 'Tickets (Done / Total)'];
        $tableRows = [];
        foreach ($versions as $version) {
            $totalIssues = $completedIssues = 0;
            foreach ($version->issuesStatusForFixVersion as $type => $count) {
                if ($type === 'done') {
                    $completedIssues += $count;
                }
                $totalIssues += $count;
            }
            $tableRows[] = [
                $version->name,
                $version->released ? '✓' : '',
                $version->releaseDate ?? '',
                sprintf('%d / %d', $completedIssues, $totalIssues)
            ];
        }
        $this->io->table($tableHeaders, $tableRows);

        return $this->io->ask('Which version are you releasing?', $defaultVersion);
    }

    private function getIssues(string $version): array
    {
        $jiraIssues = $this->jira->getIssuesByVersion($version);
        $tableHeaders = ['ID', 'Title', 'Type', 'Status'];
        $tableRows = [];
        $issues = [];
        foreach ($jiraIssues as $jiraIssue) {
            $issue = (object)[
                'id' => $jiraIssue->key,
                'title' => $jiraIssue->fields->summary,
                'type' => $jiraIssue->fields->issuetype->name,
                'status' => $jiraIssue->fields->status->name,
            ];
            $tableRows[] = [$issue->id, $issue->title, $issue->type, $issue->status];
            $issues[] = $issue;
        }
        $this->io->table($tableHeaders, $tableRows);

        return $issues;
    }

    private function createTicket(string $version, array $issues): ?string
    {
        $description = $this->templating->render('jira/release.txt.twig', [
            'issues' => $issues,
            'releaseDate' => new \DateTime('+2 days'), // TODO: from user input or version release date
            'completeDate' => new \DateTime()
        ]);

        $createResult = $this->jira->createReleaseTicket("HealthPro Release {$version}", $description);
        if ($createResult) {
            $this->io->success(sprintf(
                'Created release ticket: %s/browse/%s',
                JiraService::INSTANCE_URL,
                $createResult
            ));
            return 0;
        } else {
            $this->io->error('Failed to create release ticket');
            return 1;
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->section('Unreleased and recent versions');
        $versions = $this->jira->getVersions(5);
        if (empty($versions)) {
            $this->io->warning('Could not retrieve versions');
        }

        $version = $this->selectVersion($versions);
        if ($version === null) {
            $this->io->warning('No version selected.');
            return 1;
        }

        $this->io->section(sprintf('Tickets for release %s', $version));

        $issues = $this->getIssues($version);

        if (!$this->io->confirm('Does this look right?')) {
            $this->io->comment('Exiting.');
            return 1;
        }

        return $this->createTicket($version, $issues);
    }
}
