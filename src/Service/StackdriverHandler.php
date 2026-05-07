<?php

namespace App\Service;

use Google\Cloud\Logging\Entry;
use Google\Cloud\Logging\Logger as StackdriverLogger;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Monolog\LogRecord;
use Symfony\Component\HttpFoundation\RequestStack;

class StackdriverHandler extends AbstractProcessingHandler
{
    private EnvironmentService $env;
    private RequestStack $requestStack;
    private LoggerService $logger;
    private StackdriverLogger $stackdriverLogger;
    private NormalizerFormatter $normalizer;

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

        $this->stackdriverLogger = $this->createStackdriverLogger($clientConfig);
        $this->normalizer = new NormalizerFormatter();
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
            $record = $this->prepareRecord($record);
            $entries[] = $this->getEntryFromRecord($record);
        }
        if (!empty($entries)) {
            $this->stackdriverLogger->writeBatch($entries);
        }
    }

    /**
     * @param array<string, mixed> $clientConfig
     */
    protected function createStackdriverLogger(array $clientConfig): StackdriverLogger
    {
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

        return $stackdriverClient->logger($logName, $logOptions);
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
        $record = $this->prepareRecord($record);
        $entry = $this->getEntryFromRecord($record);
        $this->stackdriverLogger->write($entry);
    }

    private function prepareRecord(LogRecord $record): LogRecord
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

        // Recompute the formatted output after mutating the record because LogRecord::with()
        // does not preserve the previous formatted value in Monolog 3.
        $record->formatted = $this->getFormatter()->format($record);

        return $record;
    }


    private function getTraceFromHeader(?string $traceContext): string|false
    {
        $projectId = getenv('GOOGLE_CLOUD_PROJECT');
        // trace context header has the format: TRACE_ID/SPAN_ID;o=TRACE_TRUE
        if ($projectId && $traceContext && preg_match('/^([0-9a-f]+)\//', $traceContext, $m)) {
            $traceId = $m[1];
            return "projects/{$projectId}/traces/{$traceId}";
        }
        return false;
    }

    private function getEntryFromRecord(LogRecord $record): Entry
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

        return $this->stackdriverLogger->entry($this->getPayloadFromRecord($record), $entryOptions);
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayloadFromRecord(LogRecord $record): array
    {
        $payload = [
            'message' => $record->message,
            'formatted' => trim((string) $record->formatted),
            'channel' => $record->channel,
            'level' => $record->level->getName()
        ];

        $context = $this->normalizeData($record->context);
        if (isset($context['exception'])) {
            $payload['exception'] = $context['exception'];
            unset($context['exception']);
        }
        if (!empty($context)) {
            $payload['context'] = $context;
        }

        $extra = $record->extra;
        unset($extra['labels'], $extra['trace_header']);
        $extra = $this->normalizeData($extra);
        if (!empty($extra)) {
            $payload['extra'] = $extra;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        $normalized = $this->normalizer->normalizeValue($data);

        return is_array($normalized) ? $normalized : [];
    }
}
