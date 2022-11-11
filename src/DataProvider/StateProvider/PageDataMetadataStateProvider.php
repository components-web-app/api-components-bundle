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

namespace Silverback\ApiComponentsBundle\DataProvider\StateProvider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataStateProvider implements ProviderInterface
{
    public function __construct(private readonly PageDataMetadataFactoryInterface $pageDataMetadataFactory, private readonly PageDataMetadataProvider $pageDataMetadataProvider)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->pageDataMetadataProvider->createAll();
        }

        return $this->pageDataMetadataFactory->create($uriVariables['resourceClass']);
    }
}
