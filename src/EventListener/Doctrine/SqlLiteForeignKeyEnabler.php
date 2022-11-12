<?php

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
