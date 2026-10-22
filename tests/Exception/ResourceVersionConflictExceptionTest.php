<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Exception;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Exception\ResourceVersionConflictException;

final class ResourceVersionConflictExceptionTest extends TestCase
{
    public function test_a_conflict_is_a_precondition_failure_keeping_its_cause(): void
    {
        $cause = new \RuntimeException('refused');

        $exception = new ResourceVersionConflictException($cause);

        self::assertSame(412, $exception->getStatusCode());
        self::assertSame('The resource has changed since the version given in If-Match.', $exception->getMessage());
        self::assertSame($cause, $exception->getPrevious());
    }
}
