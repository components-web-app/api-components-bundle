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
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use JetBrains\PhpStorm\Pure;
use Silverback\ApiComponentsBundle\Action\Uploadable\DownloadAction;
use Silverback\ApiComponentsBundle\Action\Uploadable\UploadAction;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;

/**
 * Configures API Platform metadata for file resources.
 * POST /resource_short_name/upload (multipart/form-data)
 * POST /resource_short_name/{id}/upload (multipart/form-data)
 * GET  /resource_short_name/{id}/download/{property} (download file).
 *
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private ResourceMetadataCollectionFactoryInterface $decorated;
    private UploadableAttributeReaderInterface $uploadableFileManager;
    private PathSegmentNameGeneratorInterface $pathSegmentNameGenerator;

    public function __construct(ResourceMetadataCollectionFactoryInterface $decorated, UploadableAttributeReaderInterface $annotationReader, PathSegmentNameGeneratorInterface $pathSegmentNameGenerator)
    {
        $this->decorated = $decorated;
        $this->uploadableFileManager = $annotationReader;
        $this->pathSegmentNameGenerator = $pathSegmentNameGenerator;
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        $resourceMetadata = $this->decorated->create($resourceClass);
        if (!$this->uploadableFileManager->isConfigured($resourceClass)) {
            return $resourceMetadata;
        }

        $fields = $this->uploadableFileManager->getConfiguredProperties($resourceClass, false);
        $openApiRequestMultipartProperties = [];
        foreach ($fields as $field => $configuration) {
            $openApiRequestMultipartProperties[$field] = [
                'type' => 'string',
                'format' => 'binary',
            ];
        }

        /** @var ApiResource $resourceMetadatum */
        foreach ($resourceMetadata as $resourceMetadatum) {
            $resourceShortName = $resourceMetadatum->getShortName();
            $pathSegmentName = $this->pathSegmentNameGenerator->getSegmentName($resourceShortName);
            $operations = $resourceMetadatum->getOperations();
            if (!$operations) {
                continue;
            }
            /** @var Operation $operation */
            foreach ($operations as $operation) {
                if ($operation instanceof Post) {
                    $postUploadOperation = static::generatePostOperation($operation, $openApiRequestMultipartProperties, $pathSegmentName);
                    $operations->add(self::generateOperationName($postUploadOperation), $postUploadOperation);
                }
                if ($operation instanceof Get) {
                    $uploadItemOperation = self::generateUploadItemOperation($operation, $openApiRequestMultipartProperties, $pathSegmentName);
                    $uploadName = self::generateOperationName($uploadItemOperation);
                    $operations->add($uploadName, $uploadItemOperation->withName($uploadName));

                    $downloadItemOperation = self::generateDownloadItemOperation($operation, $pathSegmentName);
                    $downloadName = self::generateOperationName($downloadItemOperation);
                    $operations->add($downloadName, $downloadItemOperation->withName($downloadName));
                }
            }
        }

        return $resourceMetadata;
    }

    #[Pure]
    private static function generateOperationName(Operation $operation): string
    {
        return \sprintf(
            '_api_%s_%s%s',
            $operation->getUriTemplate(),
            strtolower($operation->getMethod()),
            $operation instanceof CollectionOperationInterface ? '_collection' : ''
        );
    }

    #[Pure]
    private static function configurePostOperation(Operation $postOperation, array $openApiRequestMultipartProperties): Operation
    {
        return $postOperation
            ->withController(UploadAction::class)
            ->withDeserialize(false)
            ->withStateless(null)
            ->withOpenapiContext([
                'requestBody' => [
                    'content' => [
                        'multipart/form-data' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $openApiRequestMultipartProperties,
                            ],
                        ],
                    ],
                ],
            ]);
    }

    #[Pure]
    private static function generatePostOperation(Post $defaultOperation, array $openApiRequestMultipartProperties, string $pathSegmentName): Operation
    {
        $path = \sprintf('/%s/upload', $pathSegmentName);
        $newPost = $defaultOperation
            ->withUriTemplate($path)
            ->withShortName($defaultOperation->getShortName())
            ->withRoutePrefix($defaultOperation->getRoutePrefix() ?? '');

        return self::configurePostOperation($newPost, $openApiRequestMultipartProperties);
    }

    #[Pure]
    private static function generateUploadItemOperation(Get $getOperation, array $openApiRequestMultipartProperties, string $pathSegmentName): Operation
    {
        $path = \sprintf('/%s/{id}/upload', $pathSegmentName);
        $newUploadPost = $getOperation
            ->withUriTemplate($path)
            ->withMethod(HttpOperation::METHOD_POST)
            ->withShortName($getOperation->getShortName())
            ->withRoutePrefix($getOperation->getRoutePrefix() ?? '');

        return self::configurePostOperation($newUploadPost, $openApiRequestMultipartProperties);
    }

    #[Pure]
    private static function generateDownloadItemOperation(Get $getOperation, string $pathSegmentName): Operation
    {
        $downloadPath = \sprintf('/%s/{id}/download/{property}', $pathSegmentName);

        return $getOperation
            ->withUriTemplate($downloadPath)
            ->withStateless(null)
            ->withController(DownloadAction::class)
            ->withSerialize(false)
            ->withShortName($getOperation->getShortName())
            ->withRoutePrefix($getOperation->getRoutePrefix() ?? '');
    }
}
