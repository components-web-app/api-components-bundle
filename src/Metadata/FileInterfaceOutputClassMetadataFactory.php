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
use ReflectionClass;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;

/**
 * This is to explicitly define the output class of any resource implementing FilterInterface
 * This is so that the Data Transformer will be called.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileInterfaceOutputClassMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;

    public function __construct(ResourceMetadataFactoryInterface $decorated)
    {
        $this->decorated = $decorated;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!is_subclass_of($resourceClass, FileInterface::class)) {
            return $resourceMetadata;
        }

        if (!$resourceMetadata->getAttribute('output')) {
            $attributes = $resourceMetadata->getAttributes() ?: [];
            $resourceMetadata = $resourceMetadata->withAttributes(array_merge($attributes, [
                'output' => [
                    'class' => $resourceClass,
                    'name' => (new ReflectionClass($resourceClass))->getShortName(),
                ],
            ]));
        }

        return $resourceMetadata;
    }
}
