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

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;

/**
 * This will add an endpoint for component resources to find out usage totals.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataFactoryInterface $decorated, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        $interfaces = class_implements($resourceClass);
        if (!\in_array(ComponentInterface::class, $interfaces, true)) {
            return $resourceMetadata;
        }

        $resourceShortName = $resourceMetadata->getShortName();
        if (!$resourceShortName) {
            throw new \RuntimeException(sprintf('Could not find short name from resource metadata for %s', $resourceClass));
        }
        $pathSegmentName = $this->pathSegmentNameGenerator->getSegmentName($resourceShortName);

        $usagePath = sprintf('/%s/{id}/usage', $pathSegmentName);

        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $itemOperations['get_usage'] = [
            'method' => 'GET',
            'stateless' => null,
            'path' => $usagePath,
            'serialize' => true,
        ];

        return $resourceMetadata->withItemOperations($itemOperations);
    }
}
