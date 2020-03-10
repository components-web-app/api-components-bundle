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
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedTrait;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class TimestampedListener
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function __invoke(ViewEvent $event)
    {
        $entity = $event->getControllerResult();
        if (!$entity instanceof TimestampedInterface) {
            return;
        }

        if (!$this->entityManager->contains($entity)) {
            $entity->setCreated(new DateTimeImmutable());
        }
        if (in_array(TimestampedTrait::class, class_uses($entity), true)) {
            $entity->modified = new DateTime();
        }
    }
}
