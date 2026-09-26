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

final class RejectedDeleteMiddleware implements Middleware
{
    public const MESSAGE = 'rejected-delete-test-double: the database refused the delete';

    private static bool $rejectDeletes = false;

    public static function setRejectDeletes(bool $rejectDeletes): void
    {
        self::$rejectDeletes = $rejectDeletes;
    }

    public static function throwWhenRejected(string $sql): void
    {
        if (self::$rejectDeletes && str_starts_with(strtoupper(ltrim($sql)), 'DELETE')) {
            throw new RejectedDeleteException(self::MESSAGE);
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
                        RejectedDeleteMiddleware::throwWhenRejected($sql);

                        return parent::prepare($sql);
                    }

                    public function query(string $sql): Result
                    {
                        RejectedDeleteMiddleware::throwWhenRejected($sql);

                        return parent::query($sql);
                    }

                    public function exec(string $sql): int|string
                    {
                        RejectedDeleteMiddleware::throwWhenRejected($sql);

                        return parent::exec($sql);
                    }
                };
            }
        };
    }
}
