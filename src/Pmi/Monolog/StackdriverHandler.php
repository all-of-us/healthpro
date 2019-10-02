<?php
namespace Pmi\Monolog;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

class StackdriverHandler extends AbstractProcessingHandler
{
    protected $loggingClient;
    protected $trace;

    public function __construct(array $clientConfig = [], $level = Logger::DEBUG, $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->loggingClient = new LoggingClient($clientConfig);
    }

    public function getTraceFromHeader($traceContext)
    {
        $projectId = getenv('GOOGLE_CLOUD_PROJECT');
        // trace context header has the format: TRACE_ID/SPAN_ID;o=TRACE_TRUE
        if ($projectId && $traceContext && preg_match('/^([0-9a-f]+)\//', $traceContext, $m)) {
            $traceId = $m[1];
            return "projects/{$projectId}/traces/{$traceId}";
        } else {
            return false;
        }
    }

    protected function getDefaultFormatter()
    {
        $formatter = new LineFormatter("%message% %context%", null, true);
        $formatter->includeStacktraces();
        $formatter->ignoreEmptyContextAndExtra();

        return $formatter;
    }

    protected function write(array $record)
    {
        /*
         * The log name could be set to 'appengine.googleapis.com%2Frequest_log' which is where the default GAE logs go,
         * but when you do that, matching the trace id doesn't add the new entry as a child of the original one.
         * Setting the log name to something custom (like 'healthpro.log'), but still under the gae_app type accomplishes
         * the goal of having the custom log merged with the original request log.
         * Unfortunately, the severity does not bubble up.
         */
        $logger = $this->loggingClient->logger('healthpro.log', ['resource' => [
            'type' => 'gae_app'
        ]]);
        $entryOptions = [
            'severity' => $record['level_name']
        ];
        if (isset($record['extra']['trace_header'])) {
            if ($trace = $this->getTraceFromHeader($record['extra']['trace_header'])) {
                $entryOptions['trace'] = $trace;
            }
        }
        $entry = $logger->entry((string) $record['formatted'], $entryOptions);
        $logger->write($entry);
    }
}
