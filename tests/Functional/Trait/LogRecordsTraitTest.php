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

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine\UnreachableDatabaseMiddleware;

final class LogRecordsTraitTest extends FunctionalTestCase
{
    use LogRecordsTrait;

    public function test_a_healthy_request_logs_no_warning(): void
    {
        static::createClient()->request('GET', '/_/health');

        self::assertResponseStatusCodeSame(200);
        self::assertNotLogged('a warning', static fn (LogRecord $record): bool => Level::Warning === $record->level);
    }

    public function test_asserting_nothing_was_logged_fails_when_it_was(): void
    {
        $client = static::createClient();
        UnreachableDatabaseMiddleware::setUnreachable(true);
        $client->request('GET', '/_/health');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Something matching "a warning" was logged');

        self::assertNotLogged('a warning', static fn (LogRecord $record): bool => Level::Warning === $record->level);
    }
}
