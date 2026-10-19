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

final class QueryCountingMiddleware implements Middleware
{
    private static int $count = 0;

    public static function reset(): void
    {
        self::$count = 0;
    }

    public static function count(): int
    {
        return self::$count;
    }

    public static function record(): void
    {
        ++self::$count;
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        QueryCountingMiddleware::record();

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        QueryCountingMiddleware::record();

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        QueryCountingMiddleware::record();

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
