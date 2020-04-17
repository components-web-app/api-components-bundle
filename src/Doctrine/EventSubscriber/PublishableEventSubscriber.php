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

use Doctrine\Common\Annotations\Reader;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentBundle\Annotation\Publishable;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableEventSubscriber implements EventSubscriber
{
    private Reader $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

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
        /** @var Publishable $annotation */
        if (!$annotation = $this->reader->getClassAnnotation($metadata->getReflectionClass(), Publishable::class)) {
            return;
        }

        /** @var NamingStrategy $namingStrategy */
        $namingStrategy = $eventArgs
            ->getEntityManager()
            ->getConfiguration()
            ->getNamingStrategy();

        $metadata->mapField([
            'fieldName' => $annotation->fieldName,
            'type' => 'date',
            'nullable' => true,
        ]);
        $metadata->mapOneToOne([
            'fieldName' => $annotation->associationName,
            'targetEntity' => $metadata->getName(),
            'joinColumns' => [
                [
                    'name' => $namingStrategy->joinKeyColumnName($metadata->getName()),
                    'referencedColumnName' => $namingStrategy->referenceColumnName(),
                    'onDelete' => 'SET NULL',
                ],
            ],
        ]);
    }
}
