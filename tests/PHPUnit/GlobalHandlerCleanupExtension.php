<?php

namespace App\Tests\PHPUnit;

use PHPUnit\Event\Test\BeforeTestMethodErrored;
use PHPUnit\Event\Test\BeforeTestMethodErroredSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodFailed;
use PHPUnit\Event\Test\BeforeTestMethodFailedSubscriber;
use PHPUnit\Event\Test\BeforeTestMethodFinished;
use PHPUnit\Event\Test\BeforeTestMethodFinishedSubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\AfterTestMethodFinished;
use PHPUnit\Event\Test\AfterTestMethodFinishedSubscriber;
use PHPUnit\Event\Test\Finished;
use PHPUnit\Event\Test\FinishedSubscriber;
use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

class GlobalHandlerCleanupExtension implements Extension
{
    /**
     * @var list<callable>
     */
    private static array $backupGlobalErrorHandlers = [];

    /**
     * @var list<callable>
     */
    private static array $backupGlobalExceptionHandlers = [];

    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscriber(new class implements PreparationStartedSubscriber {
            public function notify(PreparationStarted $event): void
            {
                GlobalHandlerCleanupExtension::snapshotGlobalErrorExceptionHandlers();
            }
        });

        $facade->registerSubscriber(new class implements FinishedSubscriber {
            public function notify(Finished $event): void
            {
                GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
            }
        });

        $facade->registerSubscriber(new class implements ErroredSubscriber {
            public function notify(Errored $event): void
            {
                GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
            }
        });

        $facade->registerSubscriber(new class implements SkippedSubscriber {
            public function notify(Skipped $event): void
            {
                GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
            }
        });

        if (interface_exists(BeforeTestMethodErroredSubscriber::class)) {
            $facade->registerSubscriber(new class implements BeforeTestMethodErroredSubscriber {
                public function notify(BeforeTestMethodErrored $event): void
                {
                    GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
                }
            });
        }

        if (interface_exists(BeforeTestMethodFailedSubscriber::class)) {
            $facade->registerSubscriber(new class implements BeforeTestMethodFailedSubscriber {
                public function notify(BeforeTestMethodFailed $event): void
                {
                    GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
                }
            });
        }

        if (interface_exists(BeforeTestMethodFinishedSubscriber::class)) {
            $facade->registerSubscriber(new class implements BeforeTestMethodFinishedSubscriber {
                public function notify(BeforeTestMethodFinished $event): void
                {
                    GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
                }
            });
        }

        if (interface_exists(AfterTestMethodFinishedSubscriber::class)) {
            $facade->registerSubscriber(new class implements AfterTestMethodFinishedSubscriber {
                public function notify(AfterTestMethodFinished $event): void
                {
                    GlobalHandlerCleanupExtension::restoreGlobalErrorExceptionHandlers();
                }
            });
        }
    }

    public static function snapshotGlobalErrorExceptionHandlers(): void
    {
        self::$backupGlobalErrorHandlers = self::activeErrorHandlers();
        self::$backupGlobalExceptionHandlers = self::activeExceptionHandlers();
    }

    public static function restoreGlobalErrorExceptionHandlers(): void
    {
        $activeErrorHandlers = self::activeErrorHandlers();
        $activeExceptionHandlers = self::activeExceptionHandlers();

        if ($activeErrorHandlers !== self::$backupGlobalErrorHandlers) {
            foreach ($activeErrorHandlers as $handler) {
                restore_error_handler();
            }

            foreach (self::$backupGlobalErrorHandlers as $handler) {
                set_error_handler($handler);
            }
        }

        if ($activeExceptionHandlers !== self::$backupGlobalExceptionHandlers) {
            foreach ($activeExceptionHandlers as $handler) {
                restore_exception_handler();
            }

            foreach (self::$backupGlobalExceptionHandlers as $handler) {
                set_exception_handler($handler);
            }
        }
    }

    /**
     * @return list<callable>
     */
    private static function activeErrorHandlers(): array
    {
        $activeErrorHandlers = [];

        while (true) {
            $previousHandler = set_error_handler(static fn () => false);

            restore_error_handler();

            if ($previousHandler === null) {
                break;
            }

            $activeErrorHandlers[] = $previousHandler;

            restore_error_handler();
        }

        $activeErrorHandlers = array_reverse($activeErrorHandlers);

        foreach ($activeErrorHandlers as $handler) {
            if (!is_callable($handler)) {
                continue;
            }

            set_error_handler($handler);
        }

        return $activeErrorHandlers;
    }

    /**
     * @return list<callable>
     */
    private static function activeExceptionHandlers(): array
    {
        $activeExceptionHandlers = [];

        while (true) {
            $previousHandler = set_exception_handler(static fn () => null);
            restore_exception_handler();

            if ($previousHandler === null) {
                break;
            }

            $activeExceptionHandlers[] = $previousHandler;
            restore_exception_handler();
        }

        $activeExceptionHandlers = array_reverse($activeExceptionHandlers);

        foreach ($activeExceptionHandlers as $handler) {
            if (!is_callable($handler)) {
                continue;
            }

            set_exception_handler($handler);
        }

        return $activeExceptionHandlers;
    }
}
