<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedResourceReport;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;

class OrphanedResourceDetector
{
    use ClassMetadataTrait;

    public function __construct(
        ManagerRegistry $registry,
        private readonly ComponentUsageMetadataFactory $usageMetadataFactory,
        private readonly PublishableAttributeReader $publishableAttributeReader,
        private readonly IriConverterInterface $iriConverter,
    ) {
        $this->initRegistry($registry);
    }

    public function detect(): OrphanedResourceReport
    {
        return new OrphanedResourceReport(
            new \DateTimeImmutable(),
            $this->findOrphanedComponentGroups(),
            $this->findEmptyComponentPositions(),
            $this->findUnusedComponents(),
        );
    }

    /**
     * @return list<string>
     */
    private function findOrphanedComponentGroups(): array
    {
        $orphans = [];
        /** @var ComponentGroup $componentGroup */
        foreach ($this->registry->getRepository(ComponentGroup::class)->findAll() as $componentGroup) {
            if ($componentGroup->pages->isEmpty() && $componentGroup->layouts->isEmpty() && $componentGroup->components->isEmpty()) {
                $orphans[] = $componentGroup;
            }
        }

        return $this->toSortedIris($orphans);
    }

    /**
     * @return list<string>
     */
    private function findEmptyComponentPositions(): array
    {
        return $this->toSortedIris(
            $this->registry->getRepository(ComponentPosition::class)->findBy(['component' => null, 'pageDataProperty' => null])
        );
    }

    /**
     * @return list<string>
     */
    private function findUnusedComponents(): array
    {
        $unused = [];
        /** @var AbstractComponent $component */
        foreach ($this->registry->getRepository(AbstractComponent::class)->findAll() as $component) {
            if ($this->isDraft($component)) {
                continue;
            }
            if (0 === $this->usageMetadataFactory->create($component)->getTotal()) {
                $unused[] = $component;
            }
        }

        return $this->toSortedIris($unused);
    }

    private function isDraft(AbstractComponent $component): bool
    {
        if (!$this->publishableAttributeReader->isConfigured($component)) {
            return false;
        }
        $associationName = $this->publishableAttributeReader->getConfiguration($component)->associationName;

        return null !== $this->getClassMetadata($component)->getFieldValue($component, $associationName);
    }

    /**
     * @param iterable<object> $resources
     *
     * @return list<string>
     */
    private function toSortedIris(iterable $resources): array
    {
        $iris = [];
        foreach ($resources as $resource) {
            $iris[] = $this->iriConverter->getIriFromResource($resource);
        }
        sort($iris);

        return $iris;
    }
}
