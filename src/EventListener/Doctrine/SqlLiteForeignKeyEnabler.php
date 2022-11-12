<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\EventListener\Doctrine;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\Event\PreFlushEventArgs;

class SqlLiteForeignKeyEnabler
{
    public function preFlush(PreFlushEventArgs $args): void
    {
        $conn = $args->getObjectManager()->getConnection();
        if (!$conn->getDatabasePlatform() instanceof SqlitePlatform) {
            return;
        }

        $conn->executeStatement('PRAGMA foreign_keys = ON;');
    }
}
