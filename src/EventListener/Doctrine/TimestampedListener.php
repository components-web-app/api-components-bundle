<?php

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
