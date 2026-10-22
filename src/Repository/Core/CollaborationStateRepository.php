<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Repository\Core;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\CollaborationState;

/**
 * @extends ServiceEntityRepository<CollaborationState>
 */
class CollaborationStateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly PublishableAttributeReader $publishableAttributeReader)
    {
        parent::__construct($registry, CollaborationState::class);
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return list<CollaborationState>
     */
    public function findByPublishedComponent(array $criteria): array
    {
        $component = $criteria['component'] ?? null;
        if ($component instanceof AbstractComponent) {
            $criteria['component'] = $this->publishedComponentOf($component) ?? $component;
        }

        return $this->findBy($criteria);
    }

    private function publishedComponentOf(AbstractComponent $component): ?AbstractComponent
    {
        $entityManager = $this->getEntityManager();
        $metadata = $entityManager->getClassMetadata($component::class);
        if (!$this->publishableAttributeReader->isConfigured($metadata->getName())) {
            return null;
        }
        $entityManager->initializeObject($component);
        $published = $metadata->getFieldValue($component, $this->publishableAttributeReader->getConfiguration($metadata->getName())->associationName);

        return $published instanceof AbstractComponent ? $published : null;
    }
}
