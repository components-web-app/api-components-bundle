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
    /**
     * @var list<string>
     */
    private static array $queries = [];

    public static function reset(): void
    {
        self::$queries = [];
    }

    public static function count(): int
    {
        return \count(self::$queries);
    }

    /**
     * @return list<string>
     */
    public static function queries(): array
    {
        return self::$queries;
    }

    /**
     * @return list<string>
     */
    public static function queriesOnTable(string $table): array
    {
        return array_values(array_filter(self::$queries, static fn (string $sql): bool => 1 === preg_match('/\b(?:FROM|INTO|UPDATE|JOIN)\s+"?' . preg_quote($table, '/') . '"?(?:\s|$)/i', $sql)));
    }

    public static function record(string $sql): void
    {
        self::$queries[] = $sql;
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        QueryCountingMiddleware::record($sql);

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        QueryCountingMiddleware::record($sql);

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        QueryCountingMiddleware::record($sql);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
