<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Monolog\Handler\TestHandler;
use Monolog\LogRecord;

trait LogRecordsTrait
{
    /**
     * @return list<LogRecord>
     */
    protected static function logRecords(): array
    {
        $handler = static::getContainer()->get('app.monolog.test_handler');
        \assert($handler instanceof TestHandler);

        return array_values($handler->getRecords());
    }

    /**
     * @param callable(LogRecord): bool $matches
     */
    protected static function assertLogged(string $description, callable $matches): void
    {
        $records = static::logRecords();

        self::assertNotEmpty(
            array_filter($records, $matches),
            \sprintf('Nothing matching "%s" was logged. Logged: %s', $description, implode('; ', array_map(static fn (LogRecord $record): string => $record->level->getName() . ' ' . $record->message, $records))),
        );
    }
}
