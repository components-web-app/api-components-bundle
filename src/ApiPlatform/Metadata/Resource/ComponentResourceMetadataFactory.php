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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentInterface;

/**
 * This will add an endpoint for component resources to find out usage totals.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentResourceMetadataFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        $interfaces = class_implements($resourceClass);
        if (!\in_array(ComponentInterface::class, $interfaces, true)) {
            return $resourceMetadata;
        }

        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $resourceMetadatum) {
            $resourceShortName = $resourceMetadatum->getShortName();
            if (!$resourceShortName) {
                throw new \RuntimeException(\sprintf('Could not find short name from resource metadata for %s', $resourceClass));
            }

            $pathSegmentName = $this->pathSegmentNameGenerator->getSegmentName($resourceShortName);
            $usagePath = \sprintf('/%s/{id}/usage', $pathSegmentName);

            $operations = $resourceMetadatum->getOperations();
            if ($operations) {
                $copyOperation = null;
                /** @var HttpOperation $operation */
                foreach ($operations as $operation) {
                    $uriVariables = $operation->getUriVariables();
                    if ($uriVariables) {
                        $copyOperation = $operation;
                        break;
                    }
                }
                if ($copyOperation) {
                    $usageOperation = $copyOperation->withUriTemplate($usagePath);
                    $operations->add('_api_' . $usagePath . '_get_usage', $usageOperation);
                }
            }
        }

        return $resourceMetadata;
    }
}
