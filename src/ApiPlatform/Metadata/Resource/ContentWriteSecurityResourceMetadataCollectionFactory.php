<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final readonly class ContentWriteSecurityResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    public const string OPT_OUT_EXTRA_PROPERTY = 'publishable_permission';

    private const array CONTENT_CLASSES = [
        Page::class,
        Layout::class,
        Route::class,
        ComponentGroup::class,
        ComponentGroupOrder::class,
        ComponentPosition::class,
        AbstractComponent::class,
        AbstractPageData::class,
    ];

    private const array WRITE_METHODS = [
        HttpOperation::METHOD_POST,
        HttpOperation::METHOD_PUT,
        HttpOperation::METHOD_PATCH,
        HttpOperation::METHOD_DELETE,
    ];

    public function __construct(
        private ResourceMetadataCollectionFactoryInterface $decorated,
        private PublishableAttributeReader $publishableAttributeReader,
        private string $permission,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        if (!$this->isContent($resourceClass)) {
            return $resourceMetadataCollection;
        }

        $permission = $this->permission($resourceClass);
        foreach ($resourceMetadataCollection as $i => $resource) {
            $operations = $resource->getOperations();
            if (null === $operations) {
                continue;
            }
            foreach ($operations as $key => $operation) {
                if (!\in_array($operation->getMethod(), self::WRITE_METHODS, true) || false === ($operation->getExtraProperties()[self::OPT_OUT_EXTRA_PROPERTY] ?? true)) {
                    continue;
                }
                $operations->add($key, $operation->withSecurity(self::security($permission, $operation->getSecurity())));
            }
            $resourceMetadataCollection[$i] = $resource->withOperations($operations);
        }

        return $resourceMetadataCollection;
    }

    private function isContent(string $resourceClass): bool
    {
        foreach (self::CONTENT_CLASSES as $contentClass) {
            if (is_a($resourceClass, $contentClass, true)) {
                return true;
            }
        }

        return false;
    }

    private function permission(string $resourceClass): string
    {
        if (!$this->publishableAttributeReader->isConfigured($resourceClass)) {
            return $this->permission;
        }

        return $this->publishableAttributeReader->getConfiguration($resourceClass)->isGranted ?? $this->permission;
    }

    private static function security(string $permission, ?string $security): string
    {
        if (null === $security || '' === $security) {
            return $permission;
        }

        return \sprintf('(%s) and (%s)', $permission, $security);
    }
}
