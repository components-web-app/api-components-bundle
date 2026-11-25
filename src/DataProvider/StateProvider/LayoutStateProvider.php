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

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LayoutStateProvider implements ProviderInterface
{
    public function __construct(
        private LayoutRepository $layoutRepository,
        private ProviderInterface $defaultProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->defaultProvider->provide($operation->withProvider(CollectionProvider::class), $uriVariables, $context);
        }

        $id = $uriVariables['id'];
        if ($id instanceof \Stringable) {
            $id = (string) $id;
        }
        if (!\is_string($id)) {
            return $this->defaultProvider->provide($operation->withProvider(ItemProvider::class), $uriVariables, $context);
        }

        return $this->layoutRepository->findOneByIdOrReference($id);
    }
}
