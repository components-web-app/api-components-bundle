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
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadableUploadAction;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReaderInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * Configures API Platform metadata for file resources.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableResourceMetadataFactory implements ResourceMetadataFactoryInterface
{
    private ResourceMetadataFactoryInterface $decorated;
    private UploadableAnnotationReaderInterface $uploadableHelper;
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataFactoryInterface $decorated, UploadableAnnotationReaderInterface $uploadableHelper, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->uploadableHelper = $uploadableHelper;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    public function create(string $resourceClass): ResourceMetadata
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->uploadableHelper->isConfigured($resourceClass)) {
            return $resourceMetadata;
        }

        $fields = $this->uploadableHelper->getConfiguredProperties($resourceClass, false, false);
        $properties = [];
        $fieldsAsSnakeCase = [];
        $camelCaseToSnakeCaseConverter = new CamelCaseToSnakeCaseNameConverter();
        foreach ($fields as $field) {
            $properties[$field] = [
                'type' => 'string',
                'format' => 'binary',
            ];
            $fieldsAsSnakeCase[] = $camelCaseToSnakeCaseConverter->normalize($field);
        }
        $resourceShortName = $resourceMetadata->getShortName();
        $pathSegmentName = $this->pathSegmentNameGenerator->getSegmentName($resourceShortName);
        $resourceMetadata = $this->getCollectionPostResourceMetadata($resourceMetadata, $properties, $pathSegmentName);

        return $this->getItemPutResourceMetadata($resourceMetadata, $properties, $pathSegmentName, $fieldsAsSnakeCase);
    }

    private function getCollectionPostResourceMetadata(ResourceMetadata $resourceMetadata, array $properties, string $pathSegmentName): ResourceMetadata
    {
        $path = sprintf('/%s/upload', $pathSegmentName);

        $collectionOperations = $resourceMetadata->getCollectionOperations() ?? [];
        $collectionOperations['post_upload'] = array_merge(['method' => 'POST'], $this->getUploadOperationConfiguration($properties, $path));

        return $resourceMetadata->withCollectionOperations($collectionOperations);
    }

    private function getItemPutResourceMetadata(ResourceMetadata $resourceMetadata, array $properties, string $pathSegmentName, array $fieldsAsSnakeCase): ResourceMetadata
    {
        $uploadPath = sprintf('/%s/{id}/upload', $pathSegmentName);

        $itemOperations = $resourceMetadata->getItemOperations() ?? [];
        $putProperties = $this->getUploadOperationConfiguration($properties, $uploadPath);
        $itemOperations['put_upload'] = array_merge(['method' => 'PUT'], $putProperties);
        $itemOperations['patch_upload'] = array_merge(['method' => 'PATCH'], $putProperties);

        $downloadPath = sprintf('/%s/{id}/download/', $pathSegmentName);
        foreach ($fieldsAsSnakeCase as $fieldName) {
            $propertyDownloadPath = $downloadPath . $fieldName;
            $itemOperations['download_' . $fieldName] = array_merge(['method' => 'GET'], $this->getDownloadOperationConfiguration($propertyDownloadPath));
        }

        return $resourceMetadata->withItemOperations($itemOperations);
    }

    private function getDownloadOperationConfiguration(string $path): array
    {
        return [
            'controller' => UploadableUploadAction::class,
            'path' => $path,
        ];
    }

    private function getUploadOperationConfiguration(array $properties, string $path): array
    {
        return [
            'controller' => UploadableUploadAction::class,
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
