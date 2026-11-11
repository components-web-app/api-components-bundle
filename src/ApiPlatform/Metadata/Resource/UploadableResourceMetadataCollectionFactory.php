<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiPlatform\Metadata\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Metadata;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operation\PathSegmentNameGeneratorInterface;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use JetBrains\PhpStorm\Pure;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\DownloadStateProvider;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\UploadStateProvider;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableResourceMetadataCollectionFactory implements ResourceMetadataCollectionFactoryInterface
{
    private const array WRITE_SECURITY_PROPERTIES = [
        'security',
        'securityMessage',
        'securityPostDenormalize',
        'securityPostDenormalizeMessage',
        'securityPostValidation',
        'securityPostValidationMessage',
    ];

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
            $itemWriteOperation = self::findItemWriteOperation($operations);
            foreach ($operations as $operation) {
                if ($operation instanceof Post) {
                    $postUploadOperation = static::generatePostOperation($operation, $openApiRequestMultipartProperties, $pathSegmentName);
                    $operations->add(self::generateOperationName($postUploadOperation), $postUploadOperation);
                }
                if ($operation instanceof Get) {
                    if (null !== $itemWriteOperation) {
                        $uploadItemOperation = self::generateUploadItemOperation($operation, $itemWriteOperation, $openApiRequestMultipartProperties, $pathSegmentName);
                        $uploadName = self::generateOperationName($uploadItemOperation);
                        $operations->add($uploadName, $uploadItemOperation->withName($uploadName));
                    }

                    $downloadItemOperation = self::generateDownloadItemOperation($operation, $pathSegmentName);
                    $downloadName = self::generateOperationName($downloadItemOperation);
                    $operations->add($downloadName, $downloadItemOperation->withName($downloadName));
                }
            }
        }

        return $resourceMetadata;
    }

    /**
     * @param iterable<Operation> $operations
     */
    private static function findItemWriteOperation(iterable $operations): ?HttpOperation
    {
        $put = null;
        foreach ($operations as $operation) {
            if ($operation instanceof Patch) {
                return $operation;
            }
            if (null === $put && $operation instanceof Put) {
                $put = $operation;
            }
        }

        return $put;
    }

    private static function withWriteSecurity(HttpOperation $operation, HttpOperation $writeOperation): HttpOperation
    {
        $secured = clone $operation;
        foreach (self::WRITE_SECURITY_PROPERTIES as $property) {
            $reflectionProperty = new \ReflectionProperty(Metadata::class, $property);
            $reflectionProperty->setValue($secured, $reflectionProperty->getValue($writeOperation));
        }

        return $secured;
    }

    #[Pure]
    private static function generateOperationName(HttpOperation $operation): string
    {
        return \sprintf(
            '_api_%s_%s%s',
            $operation->getUriTemplate(),
            strtolower($operation->getMethod()),
            $operation instanceof CollectionOperationInterface ? '_collection' : ''
        );
    }

    #[Pure]
    private static function configurePostOperation(HttpOperation $postOperation, array $openApiRequestMultipartProperties): HttpOperation
    {
        return $postOperation
            ->withExtraProperties([...$postOperation->getExtraProperties(), UploadStateProvider::OPERATION_EXTRA_PROPERTY => true])
            ->withDeserialize(false)
            ->withStateless(null);
    }

    #[Pure]
    private static function generatePostOperation(Post $defaultOperation, array $openApiRequestMultipartProperties, string $pathSegmentName): HttpOperation
    {
        $path = \sprintf('/%s/upload', $pathSegmentName);
        $newPost = $defaultOperation
            ->withUriTemplate($path)
            ->withShortName($defaultOperation->getShortName())
            ->withRoutePrefix($defaultOperation->getRoutePrefix() ?? '');

        return self::configurePostOperation($newPost, $openApiRequestMultipartProperties);
    }

    private static function generateUploadItemOperation(Get $getOperation, HttpOperation $writeOperation, array $openApiRequestMultipartProperties, string $pathSegmentName): HttpOperation
    {
        $path = \sprintf('/%s/{id}/upload', $pathSegmentName);
        $newUploadPost = self::withWriteSecurity($getOperation, $writeOperation)
            ->withUriTemplate($path)
            ->withMethod(HttpOperation::METHOD_POST)
            ->withShortName($getOperation->getShortName())
            ->withRoutePrefix($getOperation->getRoutePrefix() ?? '');

        return self::configurePostOperation($newUploadPost, $openApiRequestMultipartProperties);
    }

    #[Pure]
    private static function generateDownloadItemOperation(Get $getOperation, string $pathSegmentName): HttpOperation
    {
        $downloadPath = \sprintf('/%s/{id}/download/{property}', $pathSegmentName);

        return $getOperation
            ->withUriTemplate($downloadPath)
            ->withStateless(null)
            ->withExtraProperties([...$getOperation->getExtraProperties(), DownloadStateProvider::OPERATION_EXTRA_PROPERTY => true])
            ->withSerialize(false)
            ->withShortName($getOperation->getShortName())
            ->withRoutePrefix($getOperation->getRoutePrefix() ?? '');
    }
}
