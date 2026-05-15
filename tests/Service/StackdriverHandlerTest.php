<?php

namespace App\Tests\Service;

use App\Service\EnvironmentService;
use App\Service\LoggerService;
use App\Service\StackdriverHandler;
use Google\Cloud\Logging\Entry;
use Google\Cloud\Logging\Logger as StackdriverLogger;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class StackdriverHandlerTest extends TestCase
{
    public function testHandleWritesStructuredPayload(): void
    {
        putenv('GOOGLE_CLOUD_PROJECT=healthpro-test');

        $entry = new Entry();
        $stackdriverLogger = $this->createMock(StackdriverLogger::class);
        $stackdriverLogger->expects($this->once())
            ->method('entry')
            ->with(
                $this->callback(function ($payload) {
                    $this->assertStructuredPayload($payload);

                    return true;
                }),
                $this->callback(function ($options) {
                    $this->assertEntryOptions($options);

                    return true;
                })
            )
            ->willReturn($entry);
        $stackdriverLogger->expects($this->once())
            ->method('write')
            ->with($entry);
        $stackdriverLogger->expects($this->never())
            ->method('writeBatch');

        $handler = $this->createHandler($stackdriverLogger);
        $handler->handle($this->createRecord());
    }

    public function testHandleBatchWritesStructuredPayload(): void
    {
        putenv('GOOGLE_CLOUD_PROJECT=healthpro-test');

        $entry = new Entry();
        $stackdriverLogger = $this->createMock(StackdriverLogger::class);
        $stackdriverLogger->expects($this->once())
            ->method('entry')
            ->with(
                $this->callback(function ($payload) {
                    $this->assertStructuredPayload($payload);

                    return true;
                }),
                $this->callback(function ($options) {
                    $this->assertEntryOptions($options);

                    return true;
                })
            )
            ->willReturn($entry);
        $stackdriverLogger->expects($this->once())
            ->method('writeBatch')
            ->with([$entry]);
        $stackdriverLogger->expects($this->never())
            ->method('write');

        $handler = $this->createHandler($stackdriverLogger);
        $handler->handleBatch([$this->createRecord()]);
    }

    private function createHandler(StackdriverLogger $stackdriverLogger): StackdriverHandler
    {
        $env = $this->getMockBuilder(EnvironmentService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['isLocal'])
            ->getMock();
        $env->method('isLocal')->willReturn(false);

        $loggerService = $this->getMockBuilder(LoggerService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getLogMetaData'])
            ->getMock();
        $loggerService->method('getLogMetaData')->willReturn([
            'user' => 'test-user',
            'site' => 'test-site',
            'ip' => '127.0.0.1'
        ]);

        $request = Request::create('/rdr/v1/awardee2', 'GET');
        $request->headers->set('X-Cloud-Trace-Context', '105445aa7843bc8bf206b120001000/1;o=1');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new TestStackdriverHandler($env, $requestStack, $loggerService, $stackdriverLogger);
    }

    private function createRecord(): LogRecord
    {
        try {
            throw new \RuntimeException('boom');
        } catch (\RuntimeException $exception) {
            return new LogRecord(
                new \DateTimeImmutable('2026-03-23T14:00:00Z'),
                'app',
                Level::Critical,
                'Uncaught PHP Exception RuntimeException: "boom"',
                [
                    'exception' => $exception,
                    'request_id' => 'req-123'
                ],
                [
                    'worker' => 'php-fpm'
                ]
            );
        }
    }

    private function assertStructuredPayload($payload): void
    {
        $this->assertIsArray($payload);
        $this->assertSame('Uncaught PHP Exception RuntimeException: "boom"', $payload['message']);
        $this->assertSame('app', $payload['channel']);
        $this->assertSame('CRITICAL', $payload['level']);
        $this->assertStringContainsString('RuntimeException', $payload['formatted']);
        $this->assertStringContainsString('boom', $payload['formatted']);
        $this->assertSame(['request_id' => 'req-123'], $payload['context']);
        $this->assertSame(['worker' => 'php-fpm'], $payload['extra']);
        $this->assertSame(\RuntimeException::class, $payload['exception']['class']);
        $this->assertSame('boom', $payload['exception']['message']);
        $this->assertArrayHasKey('trace', $payload['exception']);
        $this->assertNotEmpty($payload['exception']['trace']);
    }

    private function assertEntryOptions($options): void
    {
        $this->assertSame('CRITICAL', $options['severity']);
        $this->assertSame('projects/healthpro-test/traces/105445aa7843bc8bf206b120001000', $options['trace']);
        $this->assertSame([
            'user' => 'test-user',
            'site' => 'test-site',
            'ip' => '127.0.0.1',
            'requestMethod' => 'GET',
            'requestUrl' => '/rdr/v1/awardee2'
        ], $options['labels']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $options['timestamp']);
    }
}

class TestStackdriverHandler extends StackdriverHandler
{
    private StackdriverLogger $testLogger;

    public function __construct(EnvironmentService $env, RequestStack $requestStack, LoggerService $logger, StackdriverLogger $testLogger)
    {
        $this->testLogger = $testLogger;

        parent::__construct($env, $requestStack, $logger);
    }

    protected function createStackdriverLogger(array $clientConfig): StackdriverLogger
    {
        return $this->testLogger;
    }
}
