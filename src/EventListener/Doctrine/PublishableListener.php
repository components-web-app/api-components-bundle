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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\Event\LoadClassMetadataEventArgs;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
final class PublishableListener
{
    private PublishableAttributeReader $publishableStatusChecker;

    public function __construct(PublishableAttributeReader $publishableStatusChecker)
    {
        $this->publishableStatusChecker = $publishableStatusChecker;
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        /** @var ClassMetadataInfo $metadata */
        $metadata = $eventArgs->getClassMetadata();
        if (!$this->publishableStatusChecker->isConfigured($metadata->getName())) {
            return;
        }

        $configuration = $this->publishableStatusChecker->getConfiguration($metadata->getName());

        $em = $eventArgs->getObjectManager();
        if (!$em instanceof EntityManagerInterface) {
            return;
        }
        $namingStrategy = $em->getConfiguration()->getNamingStrategy();

        if (!$metadata->hasField($configuration->fieldName)) {
            $metadata->mapField(
                [
                    'fieldName' => $configuration->fieldName,
                    'type' => 'datetime',
                    'nullable' => true,
                ]
            );
        }

        if (!$metadata->hasAssociation($configuration->associationName)) {
            $metadata->mapOneToOne(
                [
                    'fieldName' => $configuration->associationName,
                    'targetEntity' => $metadata->getName(),
                    'joinColumns' => [
                        [
                            'name' => $namingStrategy->joinKeyColumnName($metadata->getName()),
                            'referencedColumnName' => $namingStrategy->referenceColumnName(),
                            'onDelete' => 'SET NULL',
                            'nullable' => true,
                        ],
                    ],
                    'inversedBy' => $configuration->reverseAssociationName,
                ]
            );
        }

        if (!$metadata->hasAssociation($configuration->reverseAssociationName)) {
            $metadata->mapOneToOne(
                [
                    'fieldName' => $configuration->reverseAssociationName,
                    'targetEntity' => $metadata->getName(),
                    'mappedBy' => $configuration->associationName,
                ]
            );
        }
    }
}
