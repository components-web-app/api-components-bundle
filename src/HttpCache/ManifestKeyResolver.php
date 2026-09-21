<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\HttpCache;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\RuntimeException;
use ApiPlatform\Metadata\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class ManifestKeyResolver
{
    /**
     * @var \WeakMap<object, list<string>>
     */
    private \WeakMap $resolved;

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
    ) {
        $this->resolved = new \WeakMap();
    }

    /**
     * @return list<string>
     */
    public function resolve(object $entity): array
    {
        if (isset($this->resolved[$entity])) {
            return $this->resolved[$entity];
        }

        $iris = [];
        foreach ($this->findOwners($entity) as $owner) {
            try {
                $iri = $this->iriConverter->getIriFromResource($owner);
            } catch (InvalidArgumentException|RuntimeException) {
                continue;
            }
            $iris[$iri] = $iri;
        }

        $keys = array_values($iris);
        $this->resolved[$entity] = $keys;

        return $keys;
    }

    /**
     * @return iterable<AbstractPage>
     */
    private function findOwners(object $entity): iterable
    {
        if ($entity instanceof AbstractPage) {
            yield $entity;

            return;
        }

        if ($entity instanceof Route) {
            if ($page = $entity->getPage()) {
                yield $page;
            }
            if ($pageData = $entity->getPageData()) {
                yield $pageData;
            }

            return;
        }

        if ($entity instanceof Layout) {
            yield from $entity->pages;

            return;
        }

        if ($entity instanceof ComponentPosition) {
            if ($entity->componentGroup) {
                yield from $this->walkComponentGroups([$entity->componentGroup]);
            }

            return;
        }

        if ($entity instanceof ComponentGroup) {
            yield from $this->walkComponentGroups([$entity]);
        }
    }

    /**
     * @param list<ComponentGroup> $queue
     *
     * @return iterable<AbstractPage>
     */
    private function walkComponentGroups(array $queue): iterable
    {
        $visitedIds = [];

        while ($queue) {
            $componentGroup = array_shift($queue);

            $id = $componentGroup->getId()?->toString();
            if (null !== $id) {
                if (isset($visitedIds[$id])) {
                    continue;
                }
                $visitedIds[$id] = true;
            }

            yield from $componentGroup->pages;

            foreach ($componentGroup->layouts as $layout) {
                yield from $layout->pages;
            }

            foreach ($componentGroup->components as $parentComponent) {
                foreach ($parentComponent->getComponentPositions() as $position) {
                    if ($position->componentGroup) {
                        $queue[] = $position->componentGroup;
                    }
                }
            }
        }
    }
}
