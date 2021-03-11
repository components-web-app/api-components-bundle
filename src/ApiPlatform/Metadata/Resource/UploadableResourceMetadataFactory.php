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
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReaderInterface;

/**
 * Configures API Platform metadata for file resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private UploadableAnnotationReaderInterface $uploadableFileManager;
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataFactoryInterface $decorated, UploadableAnnotationReaderInterface $annotationReader, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->uploadableFileManager = $annotationReader;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->uploadableFileManager->isConfigured($resourceClass)) {
            return $resourceMetadata;
        }

        $fields = $this->uploadableFileManager->getConfiguredProperties($resourceClass, false);
        $properties = [];
        foreach ($fields as $field => $configuration) {
            $properties[$field] = [
                'type' => 'string',
                'format' => 'binary',
            ];
        }
        $resourceShortName = $resourceMetadata->getShortName();
        $pathSegmentName = $this->pathSegmentNameGenerator->getSegmentName($resourceShortName);
        $resourceMetadata = $this->getCollectionPostResourceMetadata($resourceMetadata, $properties, $pathSegmentName);

        return $this->getItemPutResourceMetadata($resourceMetadata, $properties, $pathSegmentName);
    }

    private function getCollectionPostResourceMetadata(ResourceMetadata $resourceMetadata, array $properties, string $pathSegmentName): ResourceMetadata
    {
        $path = sprintf('/%s/upload', $pathSegmentName);

        $collectionOperations = $resourceMetadata->getCollectionOperations() ?? [];
        $collectionOperations['post_upload'] = array_merge(['method' => 'POST'], $this->getUploadOperationConfiguration($properties, $path));

        return $resourceMetadata->withCollectionOperations($collectionOperations);
    }

    private function getItemPutResourceMetadata(ResourceMetadata $resourceMetadata, array $properties, string $pathSegmentName): ResourceMetadata
    {
        $uploadPath = sprintf('/%s/{id}/upload', $pathSegmentName);

        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $putProperties = $this->getUploadOperationConfiguration($properties, $uploadPath);
        $itemOperations['put_upload'] = array_merge(['method' => 'PUT'], $putProperties);
        $itemOperations['patch_upload'] = array_merge(['method' => 'PATCH'], $putProperties);

        $downloadPath = sprintf('/%s/{id}/download/{property}', $pathSegmentName);
        $itemOperations['download'] = $this->getDownloadOperationConfiguration($downloadPath);

        return $resourceMetadata->withItemOperations($itemOperations);
    }

    private function getDownloadOperationConfiguration(string $path): array
    {
        return [
            'method' => 'GET',
            'stateless' => null,
            'controller' => DownloadAction::class,
            'path' => $path,
            'serialize' => false,
        ];
    }

    private function getUploadOperationConfiguration(array $properties, string $path): array
    {
        return [
            'stateless' => null,
            'controller' => UploadAction::class,
            'path' => $path,
            'deserialize' => false,
            'openapi_context' => [
                'requestBody' => [
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $properties,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
