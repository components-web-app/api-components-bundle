<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<ComponentGroupOrder>
 */
final class ComponentGroupOrderStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ComponentGroupRepository $componentGroupRepository,
        private readonly ListedComponentPositions $listedComponentPositions,
        private readonly ComponentPositionOrderer $orderer,
        private readonly IriConverterInterface $iriConverter,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ComponentGroupOrder
    {
        $id = $uriVariables['id'] ?? null;
        $group = \is_string($id) ? $this->componentGroupRepository->findOneByIdOrReference($id) : null;
        if (!$group) {
            return null;
        }

        $groupIri = $this->iriConverter->getIriFromResource($group);
        $tags = [$groupIri => $groupIri];
        $componentPositions = [];
        $sortValues = [];
        foreach ($this->orderer->sorted($this->listedComponentPositions->of($group)) as $position) {
            $iri = $this->iriConverter->getIriFromResource($position);
            $componentPositions[] = $iri;
            $sortValues[$iri] = (int) $position->sortValue;
            $tags[$iri] = $iri;
        }

        $request = $context['request'] ?? null;
        if ($request instanceof Request) {
            $request->attributes->set('_resources', $request->attributes->get('_resources', []) + $tags);
        }

        return new ComponentGroupOrder((string) $group->getId(), $groupIri, $componentPositions, new \ArrayObject($sortValues));
    }
}
