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

namespace Silverback\ApiComponentsBundle\Metadata\Provider;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use Silverback\ApiComponentsBundle\Entity\Core\PageDataInterface;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PageDataMetadataProvider
{
    private ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory;
    private PageDataMetadataFactoryInterface $pageDataMetadataFactory;

    public function __construct(
        ResourceNameCollectionFactoryInterface $resourceNameCollectionFactory,
        PageDataMetadataFactoryInterface $pageDataMetadataFactory
    ) {
        $this->resourceNameCollectionFactory = $resourceNameCollectionFactory;
        $this->pageDataMetadataFactory = $pageDataMetadataFactory;
    }

    /**
     * @return PageDataMetadata[]|iterable
     */
    public function createAll(): iterable
    {
        foreach ($this->resourceNameCollectionFactory->create() as $pageDataResourceClass) {
            $reflectionClass = new \ReflectionClass($pageDataResourceClass);
            if (!$reflectionClass->implementsInterface(PageDataInterface::class)) {
                continue;
            }

            yield $this->pageDataMetadataFactory->create($pageDataResourceClass);
        }
    }
}
