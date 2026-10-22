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

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\PreFlushEventArgs;

final class BeforeFlushCallback
{
    /** @var (\Closure(Connection): void)|null */
    private static ?\Closure $callback = null;

    /**
     * @param (\Closure(Connection): void)|null $callback
     */
    public static function once(?\Closure $callback): void
    {
        self::$callback = $callback;
    }

    public function preFlush(PreFlushEventArgs $args): void
    {
        $callback = self::$callback;
        if (null === $callback) {
            return;
        }
        self::$callback = null;
        $callback($args->getObjectManager()->getConnection());
    }
}
