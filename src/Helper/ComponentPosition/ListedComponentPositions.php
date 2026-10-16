<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentPosition;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;

final class ListedComponentPositions
{
    public function __construct(private readonly IriConverterInterface $iriConverter)
    {
    }

    /**
     * @return list<ComponentPosition>
     */
    public function of(ComponentGroup $group): array
    {
        $listed = [];
        foreach ($group->componentPositions as $position) {
            if ($this->isListed($group, $position)) {
                $listed[] = $position;
            }
        }

        return $listed;
    }

    public function isListed(ComponentGroup $group, ComponentPosition $position): bool
    {
        $allowed = $group->allowedComponents;

        return null === $allowed || !$position->component || \in_array($this->collectionIri($position->component::class), $allowed, true);
    }

    private function collectionIri(string $class): ?string
    {
        return $this->iriConverter->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class));
    }
}
