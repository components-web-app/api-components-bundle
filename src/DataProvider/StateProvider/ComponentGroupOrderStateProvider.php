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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\Factory\ComponentPosition\ComponentGroupOrderFactory;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;

/**
 * @implements ProviderInterface<ComponentGroupOrder>
 */
final class ComponentGroupOrderStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ComponentGroupRepository $componentGroupRepository,
        private readonly ComponentGroupOrderFactory $componentGroupOrderFactory,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?ComponentGroupOrder
    {
        $id = $uriVariables['id'] ?? null;
        if (!\is_string($id)) {
            return null;
        }

        $group = $this->componentGroupRepository->findOneByIdOrReference($id);
        if (!$group) {
            return null;
        }

        return $this->componentGroupOrderFactory->create($group, $group->componentPositions);
    }
}
