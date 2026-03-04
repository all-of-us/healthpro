<?php

namespace App\Service;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

class StackdriverHandler extends AbstractProcessingHandler
{
    private $env;
    private $requestStack;
    private $logger;
    private $stackdriverLogger;

    public function __construct(EnvironmentService $env, RequestStack $requestStack, LoggerService $logger)
    {
        parent::__construct(Logger::INFO, true);

        $this->env = $env;
        $this->requestStack = $requestStack;
        $this->logger = $logger;

        $clientConfig = [];
        if ($this->env->isLocal()) {
            // Reuse service account key used for RDR auth
            $keyFile = realpath(__DIR__ . '/../../') . '/dev_config/rdr_key.json';
            $clientConfig = [
                'keyFilePath' => $keyFile
            ];
        }

        $stackdriverClient = new LoggingClient($clientConfig);

        /*
         * The log name could be set to 'appengine.googleapis.com%2Frequest_log' which is where the default GAE logs go,
         * but when you do that, matching the trace id doesn't add the new entry as a child of the original one.
         * Setting the log name to something custom (like 'healthpro.log'), but still under the gae_app type accomplishes
         * the goal of having the custom log merged with the original request log.
         * Unfortunately, the severity does not bubble up.
         */
        $logName = 'healthpro.log';
        $logOptions = [
            'resource' => [
                'type' => 'gae_app'
            ]
        ];
        $this->stackdriverLogger = $stackdriverClient->logger($logName, $logOptions);
    }

    public function handleBatch(array $records): void
    {
        $entries = [];
        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                continue;
            }
            if (count($this->processors) > 0) {
                $record = $this->processRecord($record);
            }
            $record->formatted = $this->getFormatter()->format($record);
            $entries[] = $this->getEntryFromRecord($record);
        }
        if (!empty($entries)) {
            $this->stackdriverLogger->writeBatch($entries);
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        $formatter = new LineFormatter('%message% %context%', null, true);
        $formatter->includeStacktraces();
        $formatter->ignoreEmptyContextAndExtra();

        return $formatter;
    }

    protected function write(LogRecord $record): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $siteMetaData = $this->logger->getLogMetaData();
        $extra = $record->extra;
        $extra['labels'] = [
            'user' => $siteMetaData['user'],
            'site' => $siteMetaData['site'],
            'ip' => $siteMetaData['ip']
        ];
        if ($request) {
            $extra['labels']['requestMethod'] = $request->getMethod();
            $extra['labels']['requestUrl'] = $request->getPathInfo();
            if ($traceHeader = $request->headers->get('X-Cloud-Trace-Context')) {
                $extra['trace_header'] = $traceHeader;
            }
        }
        $record = $record->with(extra: $extra);
        $entry = $this->getEntryFromRecord($record);
        $this->stackdriverLogger->write($entry);
    }


    private function getTraceFromHeader($traceContext)
    {
        $projectId = getenv('GOOGLE_CLOUD_PROJECT');
        // trace context header has the format: TRACE_ID/SPAN_ID;o=TRACE_TRUE
        if ($projectId && $traceContext && preg_match('/^([0-9a-f]+)\//', $traceContext, $m)) {
            $traceId = $m[1];
            return "projects/{$projectId}/traces/{$traceId}";
        }
        return false;
    }

    private function getEntryFromRecord(LogRecord $record)
    {
        $entryOptions = [
            'severity' => $record->level->getName(),
            'timestamp' => $record->datetime
        ];
        if (isset($record->extra['labels'])) {
            $entryOptions['labels'] = $record->extra['labels'];
        }
        if (isset($record->extra['trace_header'])) {
            if ($trace = $this->getTraceFromHeader($record->extra['trace_header'])) {
                $entryOptions['trace'] = $trace;
            }
        }

        return $this->stackdriverLogger->entry((string) $record->formatted, $entryOptions);
    }
}
