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
use Doctrine\DBAL\Driver\Statement;

final class BeforeTransactionCallback implements Middleware
{
    private static ?\Closure $callback = null;

    public static function once(?\Closure $callback): void
    {
        self::$callback = $callback;
    }

    public static function runBefore(?string $sql = null): void
    {
        $callback = self::$callback;
        if (null === $callback || (null !== $sql && !str_starts_with(strtoupper(ltrim($sql)), 'SAVEPOINT'))) {
            return;
        }
        self::$callback = null;
        $callback();
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): Connection
            {
                return new class(parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function beginTransaction(): void
                    {
                        BeforeTransactionCallback::runBefore();
                        parent::beginTransaction();
                    }

                    public function prepare(string $sql): Statement
                    {
                        BeforeTransactionCallback::runBefore($sql);

                        return parent::prepare($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        BeforeTransactionCallback::runBefore($sql);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
