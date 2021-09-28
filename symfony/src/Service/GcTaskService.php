<?php

namespace App\Service;

use Google\Cloud\Tasks\V2\CloudTasksClient;
use Google\Cloud\Tasks\V2\HttpMethod;
use Google\Cloud\Tasks\V2\HttpRequest;
use Google\Cloud\Tasks\V2\Task;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class GcTaskService
{
    protected $cloudTaskClient;
    protected $projectId;
    protected $config = [];

    private const LOCATION_ID = 'us-central1';

    public function __construct(EnvironmentService $environment, KernelInterface $appKernel, ParameterBagInterface $params)
    {
        $basePath = $appKernel->getProjectDir();
        // Note that when installed in ./symfony, the development credentials are a level down
        if ($environment->isLocal() && file_exists($basePath . '/../dev_config/rdr_key.json')) {
            $this->config['key_file'] = $basePath . '/../dev_config/rdr_key.json';
            $this->cloudTaskClient = new CloudTasksClient([
                'credentials' => $this->config['key_file']
            ]);
        } else {
            $this->cloudTaskClient = new CloudTasksClient();
        }

        if ($params->has('gc_task_project_id')) {
            $this->projectId = $params->get('gc_task_project_id');
        }
    }

    public function createTask(array $params = []): Task
    {
        if (!$params['url']) {
            throw new \Exception('No URL set for Task.');
        }
        $httpRequest = new HttpRequest();
        $httpRequest->setUrl($params['url']);
        if (isset($params['method']) && $params['method'] === 'POST') {
            $httpRequest->setHttpMethod(HttpMethod::POST);
        }
        $httpRequest->setBody(isset($params['body']) ? $params['body'] : null);

        $task = new Task();
        $task->setHttpRequest($httpRequest);
        return $task;
    }

    public function createQueue(string $queueId): string
    {
        return $this->cloudTaskClient->queueName($this->projectId, self::LOCATION_ID, $queueId);
    }

    public function addTaskToQueue(string $queue, Task $task): Task
    {
        return $this->cloudTaskClient->createTask($queue, $task);
    }

    public function close()
    {
        return $this->cloudTaskClient->close();
    }
}
