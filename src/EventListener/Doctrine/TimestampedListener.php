<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use DateTime;
use DateTimeImmutable;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedListener
{
    public function prePersist(LifecycleEventArgs $args): void
    {
        $timestamped = $args->getObject();
        if (!$timestamped instanceof TimestampedInterface) {
            return;
        }

        $timestamped->setCreated(new DateTimeImmutable());
        $timestamped->setModified(new DateTime());
    }

    public function preUpdate(LifecycleEventArgs $args): void
    {
        $timestamped = $args->getObject();
        if (!$timestamped instanceof TimestampedInterface) {
            return;
        }

        $timestamped->setModified(new DateTime());
    }
}
