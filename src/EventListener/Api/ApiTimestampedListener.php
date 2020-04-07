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

namespace Silverback\ApiComponentBundle\EventListener\Api;

use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\Utility\TimestampedInterface;
use Symfony\Component\HttpKernel\Event\ViewEvent;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ApiTimestampedListener
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
        $entity->setModified(new DateTime());
    }
}
