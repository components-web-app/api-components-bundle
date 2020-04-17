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

namespace Silverback\ApiComponentBundle\Doctrine\EventSubscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentBundle\Entity\Utility\PublishableInterface;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventSubscriber implements EventSubscriber
{
    /**
     * {@inheritdoc}
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::loadClassMetadata,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if (!is_a($metadata->getName(), PublishableInterface::class, true)) {
            return;
        }

        $metadata->mapOneToOne([
            'fieldName' => 'publishedResource',
            'targetEntity' => $metadata->getName(),
            'joinColumns' => [
                [
                    'name' => 'published_resource_id',
                    'referencedColumnName' => $metadata->getSingleIdentifierColumnName(),
                ],
            ],
        ]);
    }
}