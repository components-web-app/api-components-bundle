<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Metadata;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentBundle\Publishable\PublishableHelper;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Builds and add validation group for published resources.
 *
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class PublishableMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private PublishableHelper $publishableHelper;
    private RequestStack $requestStack;

    public function __construct(ResourceMetadataFactoryInterface $decorated, PublishableHelper $publishableHelper, RequestStack $requestStack)
    {
        $this->decorated = $decorated;
        $this->publishableHelper = $publishableHelper;
        $this->requestStack = $requestStack;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->publishableHelper->isPublishable($resourceClass)) {
            return $resourceMetadata;
        }

        // Try to retrieve data from request
        // todo Find a better way to retrieve the data
        if (!($request = $this->requestStack->getCurrentRequest()) || empty($data = $request->attributes->get('data'))) {
            return $resourceMetadata;
        }

        if ($this->publishableHelper->hasPublicationDate($data)) {
            $configuration = $this->publishableHelper->getConfiguration($resourceClass);
            $attributes = $resourceMetadata->getAttributes() ?: [];

            $resourceMetadata = $resourceMetadata->withAttributes(array_unique(array_merge_recursive($attributes, [
                'validation_groups' => null !== $configuration->validationGroups ? $configuration->validationGroups : [
                    'Default',
                    "$resourceClass:published",
                ],
            ])));
        }

        return $resourceMetadata;
    }
}
