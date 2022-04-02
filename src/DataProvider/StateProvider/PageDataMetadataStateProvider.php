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

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataStateProvider implements ProviderInterface
{
    private PageDataMetadataFactoryInterface $pageDataMetadataFactory;
    private PageDataMetadataProvider $pageDataMetadataProvider;

    public function __construct(PageDataMetadataFactoryInterface $pageDataMetadataFactory, PageDataMetadataProvider $pageDataMetadataProvider)
    {
        $this->pageDataMetadataFactory = $pageDataMetadataFactory;
        $this->pageDataMetadataProvider = $pageDataMetadataProvider;
    }

    public function provide(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = [])
    {
        /** @var Operation */
        $operation = $context['operation'];

        if ($operation->isCollection()) {
            return $this->pageDataMetadataProvider->createAll();
        }

        return $this->pageDataMetadataFactory->create($uriVariables['resourceClass']);
    }

    public function supports(string $resourceClass, array $uriVariables = [], ?string $operationName = null, array $context = []): bool
    {
        return PageDataMetadata::class === $resourceClass;
    }
}
