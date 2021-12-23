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

namespace Silverback\ApiComponentsBundle\DataProvider\Collection;

use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\DataProvider\RestrictedDataProviderInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataCollectionProvider implements CollectionDataProviderInterface, RestrictedDataProviderInterface
{
    private PageDataMetadataProvider $pageDataMetadataProvider;

    public function __construct(PageDataMetadataProvider $pageDataMetadataProvider)
    {
        $this->pageDataMetadataProvider = $pageDataMetadataProvider;
    }

    public function getCollection(string $resourceClass, string $operationName = null)
    {
        return $this->pageDataMetadataProvider->createAll();
    }

    public function supports(string $resourceClass, string $operationName = null, array $context = []): bool
    {
        return PageDataMetadata::class === $resourceClass;
    }
}
