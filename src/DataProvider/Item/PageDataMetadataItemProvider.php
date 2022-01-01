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

namespace Silverback\ApiComponentsBundle\DataProvider\Item;

use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataItemProvider implements ItemDataProviderInterface, RestrictedDataProviderInterface
{
    private PageDataMetadataFactoryInterface $pageDataMetadataFactory;

    public function __construct(PageDataMetadataFactoryInterface $pageDataMetadataFactory)
    {
        $this->pageDataMetadataFactory = $pageDataMetadataFactory;
    }

    public function getItem(string $resourceClass, $id, string $operationName = null, array $context = []): ?PageDataMetadata
    {
        return $this->pageDataMetadataFactory->create($id);
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return PageDataMetadata::class === $resourceClass;
    }
}
