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
use Silverback\ApiComponentBundle\Action\File\FileAction;
use Silverback\ApiComponentBundle\Annotation\File;
use Silverback\ApiComponentBundle\Helper\FileHelper;

/**
 * Configures API Platform metadata for file resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class FileResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private FileHelper $fileHelper;

    public function __construct(ResourceMetadataFactoryInterface $decorated, FileHelper $fileHelper)
    {
        $this->decorated = $decorated;
        $this->fileHelper = $fileHelper;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->fileHelper->isConfigured($resourceClass)) {
            return $resourceMetadata;
        }
        $fileConfiguration = $this->fileHelper->getConfiguration($resourceClass);

        $resourceMetadata = $this->getCollectionPostResourceMetadata($resourceMetadata, $fileConfiguration);

        return $this->getItemPutResourceMetadata($resourceMetadata, $fileConfiguration);
    }

    private function getCollectionPostResourceMetadata(ResourceMetadata $resourceMetadata, File $fileConfiguration): ResourceMetadata
    {
        $collectionOperations = $resourceMetadata->getCollectionOperations() ?? [];
        $collectionOperations['post'] = array_replace_recursive(
            $this->getOperationConfiguration($fileConfiguration),
            $collectionOperations['post'] ?? []
        );

        return $resourceMetadata->withCollectionOperations($collectionOperations);
    }

    private function getItemPutResourceMetadata(ResourceMetadata $resourceMetadata, File $fileConfiguration): ResourceMetadata
    {
        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $itemOperations['put'] = array_replace_recursive(
            $this->getOperationConfiguration($fileConfiguration),
            $itemOperations['put'] ?? []
        );

        return $resourceMetadata->withItemOperations($itemOperations);
    }

    private function getOperationConfiguration(File $fileConfiguration): array
    {
        return [
            'controller' => FileAction::class,
            'deserialize' => false,
            'openapi_context' => [
                'requestBody' => [
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    $fileConfiguration->fileFieldName => [
                                        'type' => 'string',
                                        'format' => 'binary',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
