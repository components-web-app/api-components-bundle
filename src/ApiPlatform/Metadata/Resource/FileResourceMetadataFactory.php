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

namespace Silverback\ApiComponentBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Silverback\ApiComponentBundle\Action\File\CreateFileAction;
use Silverback\ApiComponentBundle\Helper\FileHelper;

/**
 * Configures API Platform metadata for media object resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private FileHelper $mediaObjectHelper;

    public function __construct(ResourceMetadataFactoryInterface $decorated, FileHelper $mediaObjectHelper)
    {
        $this->decorated = $decorated;
        $this->mediaObjectHelper = $mediaObjectHelper;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->mediaObjectHelper->isConfigured($resourceClass)) {
            return $resourceMetadata;
        }
        $mediaConfiguration = $this->mediaObjectHelper->getConfiguration($resourceClass);

        $attributes = $resourceMetadata->getAttributes() ?: [];
        $collectionOperations = $resourceMetadata->getAttribute('collectionOperations') ?? [];

        $collectionOperations['post'] = array_replace_recursive(
            $collectionOperations['post'] ?? [],
            [
                'controller' => CreateFileAction::class,
                'deserialize' => false,
                'openapi_context' => [
                    'requestBody' => [
                        'content' => [
                            'multipart/form-data' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        $mediaConfiguration->fileFieldName => [
                                            'type' => 'string',
                                            'format' => 'binary',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        // Because we have defined a post operation, if we do not add this then we cannot get collections of media objects
        // User can disable this functionality in their annotation
        if (!$mediaConfiguration->disableGetCollection && !isset($collectionOperations['get'])) {
            $collectionOperations['get'] = [];
        }

        $resourceMetadata->withAttributes(
            array_merge($attributes, [
                'collectionOperations' => $collectionOperations,
            ])
        );

        return $resourceMetadata;
    }
}
