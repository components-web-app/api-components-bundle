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
use ApiPlatform\Core\Operation\PathSegmentNameGeneratorInterface;
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
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataFactoryInterface $decorated, FileHelper $fileHelper, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->fileHelper = $fileHelper;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
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
        $resourceShortName = $resourceMetadata->getShortName();
        $path = sprintf('/%s/upload', $this->pathSegmentNameGenerator->getSegmentName($resourceShortName));

        $collectionOperations = $resourceMetadata->getCollectionOperations() ?? [];
        $collectionOperations['post_upload'] = array_replace_recursive(
            $this->getOperationConfiguration($fileConfiguration, $path),
            $collectionOperations['post'] ?? []
        );

        return $resourceMetadata->withCollectionOperations($collectionOperations);
    }

    private function getItemPutResourceMetadata(ResourceMetadata $resourceMetadata, File $fileConfiguration): ResourceMetadata
    {
        $resourceShortName = $resourceMetadata->getShortName();
        $path = sprintf('/%s/{id}/upload', $this->pathSegmentNameGenerator->getSegmentName($resourceShortName));

        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $itemOperations['put_upload'] = array_replace_recursive(
            $this->getOperationConfiguration($fileConfiguration, $path),
            $itemOperations['put'] ?? []
        );

        return $resourceMetadata->withItemOperations($itemOperations);
    }

    private function getOperationConfiguration(File $fileConfiguration, string $path): array
    {
        return [
            'controller' => FileAction::class,
            'deserialize' => false,
            'path' => $path,
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
