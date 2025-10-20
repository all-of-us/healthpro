<?php

namespace App\Command;

use App\Service\JiraService;
use stdClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment as Templating;

class ReleaseTicketCommand extends Command
{
    private $jira;
    private $io;
    private $templating;

    private $targetReleaseDate;
    private $defaultAccountIds = []; // defined by jira_account_ids config
    private $developerAccountIds = []; // all assignees of tickets

    private static $appIds = [
        'Stable' => 'pmi-hpo-test',
        'Production' => 'healthpro-prod'
    ];

    public function __construct(JiraService $jira, Templating $templating, ParameterBagInterface $params)
    {
        $this->jira = $jira;
        $this->templating = $templating;
        if ($params->has('jira_account_ids')) {
            $this->defaultAccountIds = $params->get('jira_account_ids');
        }
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('pmi:jira')
            ->setDescription('Create Jira release ticket')
            ->addOption(
                'comment',
                null,
                InputOption::VALUE_REQUIRED,
                'Comment type'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $comment = $input->getOption('comment');

        if ($comment === 'approval') {
            $this->io->section('Approval request comment');
            $ticketId = $this->io->ask('Enter ticket id');
            return $this->createApprovalRequestComment($ticketId);
        }

        $version = $this->selectVersion();
        if ($version === null) {
            $this->io->warning('No version selected.');
            return 1;
        }

        $componentId = $this->selectComponent();

        if ($comment === 'file') {
            $file = $this->selectDeployFile();
            if ($file === null) {
                $this->io->warning('No deploy files found.');
                return 1;
            }

            $env = $this->selectEnvironment();

            $this->io->section('Attach deploy output');
            $ticketId = $this->io->ask('Enter ticket id');
            return $this->attachDeployOutput($version, $env, $file, $ticketId);
        }

        $issues = $this->getIssues($version);
        if ($issues === null) {
            $this->io->comment('Exiting.');
            return 1;
        }

        return $this->createTicket($version, $issues, $componentId);
    }

    private function selectVersion(): ?string
    {
        $this->io->section('Unreleased and recent versions');

        $versions = $this->jira->getVersions(5);
        if (empty($versions)) {
            $this->io->warning('Could not retrieve versions');
        }
        $defaultVersion = null;
        // Set default version to first version if unreleased
        if (isset($versions[0]) && !$versions[0]->released) {
            $defaultVersion = $versions[0]->name;
        }
        $tableHeaders = ['Version', 'Released?', 'Release Date', 'Tickets (Done / Total)'];
        $tableRows = [];
        $releaseDateMap = [];
        /** @var stdClass $version */
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
            // If there are unreleased versions with release dates set, we want the last one
            if (!$version->released && isset($version->releaseDate)) {
                $defaultVersion = $version->name;
            }

            if (isset($version->releaseDate)) {
                $releaseDateMap[$version->name] = $version->releaseDate;
            }
        }
        $this->io->table($tableHeaders, $tableRows);

        $version = $this->io->ask('Which version are you releasing?', $defaultVersion);
        if ($version && isset($releaseDateMap[$version])) {
            $this->targetReleaseDate = new \DateTime($releaseDateMap[$version]);
        }

        return $version;
    }

    private function selectComponent(): ?string
    {
        $this->io->section('Components List');
        $components = $this->jira->getComponents();
        if (empty($components)) {
            $this->io->warning('Could not retrieve components');
            return '';
        }
        $defaultComponent = null;
        $tableHeaders = ['Id', 'Component Name'];
        $tableRows = [];
        foreach ($components as $component) {
            $tableRows[] = [
                $component->id,
                $component->name
            ];
            if ($component->id === JiraService::DESTINATION_COMPONENT_ID) {
                $defaultComponent = JiraService::DESTINATION_COMPONENT_ID;
            }
        }
        $this->io->table($tableHeaders, $tableRows);
        $component = $this->io->ask('Please select a component', $defaultComponent);
        return $component;
    }

    private function selectEnvironment(): ?string
    {
        return $this->io->choice('Please select environment', ['Stable', 'Production']);
    }

    private function selectDeployFile(): ?string
    {
        $deployFiles = glob('deploy_*.txt');
        if (!empty($deployFiles)) {
            rsort($deployFiles);
            return $this->io->choice('Please select deploy file', $deployFiles, 0);
        }
        return null;
    }

    private function getIssues(string $version): ?array
    {
        $this->io->section(sprintf('Tickets for release %s', $version));

        $jiraIssues = $this->jira->getIssuesByVersion($version);
        $tableHeaders = ['ID', 'Title', 'Type', 'Status', 'Assignee'];
        $tableRows = [];
        $issues = [];
        foreach ($jiraIssues as $jiraIssue) {
            $issue = (object) [
                'id' => $jiraIssue->key,
                'title' => $jiraIssue->fields->summary ?? '',
                'type' => $jiraIssue->fields->issuetype->name ?? '',
                'status' => $jiraIssue->fields->status->name ?? '',
                'assignee' => $jiraIssue->fields->assignee->displayName ?? ''
            ];
            $tableRows[] = [
                $issue->id,
                strlen($issue->title) > 50 ? (substr($issue->title, 0, 47) . '...') : $issue->title,
                $issue->type,
                $issue->status,
                $issue->assignee
            ];
            $issues[] = $issue;
            if (isset($jiraIssue->fields->assignee->accountId)) {
                $this->developerAccountIds[] = $jiraIssue->fields->assignee->accountId;
            }
        }
        $this->developerAccountIds = array_unique($this->developerAccountIds);
        $this->io->table($tableHeaders, $tableRows);

        if ($this->io->confirm('Does this look right?')) {
            return $issues;
        }
        return null;
    }

    private function createTicket(string $version, array $issues, string $componentId): int
    {
        if (!$this->targetReleaseDate) {
            $this->io->text('No release date has been specified for this release.');
            $this->io->text(sprintf('Specify the date that should be used for the production "Needed By Date/Event" section in the release ticket. Whatever you enter will be passed to the DateTime constructor. (Today is: %s)', date('D n/j/Y')));
            $this->targetReleaseDate = new \DateTime($this->io->ask('Target release date:', '+2 days'));
        }

        $developerIds = $this->developerAccountIds;
        $changeManagerIds = $this->defaultAccountIds['change'] ?? [];
        $testerIds = array_unique(array_merge(
            $this->defaultAccountIds['qa'] ?? [],
            $this->defaultAccountIds['dev'] ?? []
        ));
        $descriptionJson = $this->templating->render('jira/release.json.twig', [
            'issues' => $issues,
            'releaseDate' => $this->targetReleaseDate,
            'completeDate' => new \DateTime(),
            'developers' => $developerIds,
            'changeManagers' => $changeManagerIds,
            'testers' => $testerIds
        ]);
        $description = json_decode($descriptionJson, true);

        $createResult = $this->jira->createReleaseTicket("HealthPro Release {$version}", $description, $componentId);
        if ($createResult) {
            $this->io->success(sprintf(
                'Created release ticket: %s/browse/%s',
                JiraService::INSTANCE_URL,
                $createResult
            ));
            return 0;
        }
        $this->io->error('Failed to create release ticket');
        return 1;
    }

    private function createApprovalRequestComment($ticketId): int
    {
        $businessApprovalIds = $this->defaultAccountIds['business'];
        $securityApprovalIds = $this->defaultAccountIds['security'];
        $commentJson = $this->templating->render('jira/approval-request-comment.json.twig', [
            'businessApprovals' => $businessApprovalIds,
            'securityApprovals' => $securityApprovalIds
        ]);
        $comment = json_decode($commentJson, true);

        $createResult = $this->jira->createComment($ticketId, $comment);
        if ($createResult) {
            $this->io->success(sprintf(
                'Approval request commented: %s/browse/%s',
                JiraService::INSTANCE_URL,
                $ticketId
            ));
            return 0;
        }
        $this->io->error('Failed to create approval request');
        return 1;
    }

    private function createDeployComment($ticketId, $env, $deployFileName): bool
    {
        $commentJson = $this->templating->render('jira/deploy-output-comment.json.twig', [
            'env' => $env,
            'deployFileName' => $deployFileName
        ]);
        $comment = json_decode($commentJson, true);
        return $this->jira->createComment($ticketId, $comment);
    }

    private function attachDeployOutput($version, $env, $file, $ticketId): int
    {
        $appDir = realpath(__DIR__ . '/../..');
        $path = $appDir . "/{$file}";
        $appId = self::$appIds[$env];
        $deployFileName = "{$appId}.release-{$version}.txt";
        $attachResult = $this->jira->attachFile($ticketId, $path, $deployFileName);
        if ($attachResult) {
            $createResult = $this->createDeployComment($ticketId, $env, $deployFileName);
            if ($createResult) {
                $message = 'Deploy output attached and comment created.';
            } else {
                $message = 'Deploy output attached but comment not created.';
            }
            $this->io->success(sprintf(
                $message . ' %s/browse/%s',
                JiraService::INSTANCE_URL,
                $ticketId
            ));
            return 0;
        }
        $this->io->error('Failed to attach deploy output');
        return 1;
    }
}
