<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

final class UnreachableDatabaseMiddleware implements Middleware
{
    public const MESSAGE = 'unreachable-database-test-double: could not connect to secret-host';

    private static bool $unreachable = false;

    public static function setUnreachable(bool $unreachable): void
    {
        self::$unreachable = $unreachable;
    }

    public static function throwWhenUnreachable(): void
    {
        if (self::$unreachable) {
            throw new UnreachableDatabaseException(self::MESSAGE);
        }
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        UnreachableDatabaseMiddleware::throwWhenUnreachable();

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        UnreachableDatabaseMiddleware::throwWhenUnreachable();

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        UnreachableDatabaseMiddleware::throwWhenUnreachable();

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
