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

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Imagine\CacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableFileManager
{
    use ClassMetadataTrait;

    private UploadableAttributeReader $annotationReader;
    private FilesystemProvider $filesystemProvider;
    private FlysystemDataLoader $flysystemDataLoader;
    private FileInfoCacheManager $fileInfoCacheManager;
    private ?CacheManager $imagineCacheManager;
    private ?FilterService $filterService;
    private ArrayCollection $deletedFields;

    public function __construct(ManagerRegistry $registry, UploadableAttributeReader $annotationReader, FilesystemProvider $filesystemProvider, FlysystemDataLoader $flysystemDataLoader, FileInfoCacheManager $fileInfoCacheManager, ?CacheManager $imagineCacheManager, ?FilterService $filterService = null)
    {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->filesystemProvider = $filesystemProvider;
        $this->flysystemDataLoader = $flysystemDataLoader;
        $this->fileInfoCacheManager = $fileInfoCacheManager;
        $this->imagineCacheManager = $imagineCacheManager;
        $this->filterService = $filterService;
        $this->deletedFields = new ArrayCollection();
    }

    public function addDeletedField($field): void
    {
        $this->deletedFields->add($field);
    }

    public function processClonedUploadable(object $oldObject, object $newObject): object
    {
        if (!$this->annotationReader->isConfigured($oldObject)) {
            throw new \InvalidArgumentException('The old object is not configured as uploadable');
        }

        if (\get_class($oldObject) !== \get_class($newObject)) {
            throw new \InvalidArgumentException('The objects must be the same class');
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $configuredProperties = $this->annotationReader->getConfiguredProperties($oldObject, false);
        foreach ($configuredProperties as $fieldConfiguration) {
            if ($propertyAccessor->getValue($oldObject, $fieldConfiguration->property)) {
                $newPath = $this->copyFilepath($oldObject, $fieldConfiguration);
                $propertyAccessor->setValue($newObject, $fieldConfiguration->property, $newPath);
            }
        }

        return $newObject;
    }

    public function setUploadedFilesFromFileBag(object $object, FileBag $fileBag): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, false);

        /**
         * @var UploadableField[] $configuredProperties
         */
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            if ($file = $fileBag->get($fileProperty)) {
                $propertyAccessor->setValue($object, $fileProperty, $file);
            }
        }
    }

    public function storeFilesMetadata(object $object): void
    {
        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true);
        $classMetadata = $this->getClassMetadata($object);

        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            // Let the data loader which should be configured for imagine to know which adapter to use
            $this->flysystemDataLoader->setAdapter($fieldConfiguration->adapter);

            $filename = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($filename && $object instanceof ImagineFiltersInterface && $this->filterService) {
                $filters = $object->getImagineFilters($fileProperty, null);
                foreach ($filters as $filter) {
                    // This will trigger the cached file to be store
                    // When cached files are store we save the file info
                    $this->filterService->getUrlOfFilteredImage($filename, $filter);
                }
            }
        }
    }

    public function persistFiles(object $object): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true);
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            // this is null if null is submitted as the value... also null if not submitted
            /** @var File|UploadedDataUriFile|null $file */
            $file = $propertyAccessor->getValue($object, $fileProperty);
            if (!$file) {
                // so we need to know if it was a deleted field from the denormalizer
                if ($this->deletedFields->contains($fieldConfiguration->property)) {
                    $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
                    $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
                }
                continue;
            }

            $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);

            $path = $fieldConfiguration->prefix ?? '';
            $path .= $file->getFilename();
            $stream = fopen($file->getRealPath(), 'r');
            $filesystem->writeStream(
                $path,
                $stream,
                [
                    'mimetype' => $file->getMimeType(),
                    'metadata' => [
                        'contentType' => $file->getMimeType()
                    ]
                ]
            );
            $classMetadata->setFieldValue($object, $fieldConfiguration->property, $path);
            $propertyAccessor->setValue($object, $fileProperty, null);
        }
    }

    public function deleteFiles(object $object): void
    {
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true);
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
        }
    }

    private function deleteFileForField(object $object, ClassMetadata $classMetadata, UploadableField $fieldConfiguration): void
    {
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        if ($currentFilepath) {
            $this->removeFilepath($object, $fieldConfiguration);
        }
    }

    public function getFileResponse(object $object, string $property, bool $forceDownload = false): Response
    {
        try {
            $reflectionProperty = new \ReflectionProperty($object, $property);
        } catch (\ReflectionException $exception) {
            throw new NotFoundHttpException($exception->getMessage());
        }
        if (!$this->annotationReader->isFieldConfigured($reflectionProperty)) {
            throw new NotFoundHttpException(sprintf('field configuration not found for %s', $property));
        }

        $propertyConfiguration = $this->annotationReader->getPropertyConfiguration($reflectionProperty);

        $filesystem = $this->filesystemProvider->getFilesystem($propertyConfiguration->adapter);

        $classMetadata = $this->getClassMetadata($object);

        $filePath = $classMetadata->getFieldValue($object, $propertyConfiguration->property);
        if (empty($filePath)) {
            return new Response('The file path for this resource is empty', Response::HTTP_NOT_FOUND);
        }
        $response = new StreamedResponse();
        $response->setCallback(
            static function () use ($filesystem, $filePath) {
                $outputStream = fopen('php://output', 'w');
                $fileStream = $filesystem->readStream($filePath);
                stream_copy_to_stream($fileStream, $outputStream);
            }
        );
        $response->headers->set('Content-Type', $filesystem->mimeType($filePath));

        $disposition = HeaderUtils::makeDisposition($forceDownload ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE, $filePath);
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    private function removeFilepath(object $object, UploadableField $fieldConfiguration): void
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        $this->fileInfoCacheManager->deleteCaches([$currentFilepath], [null]);
        if ($this->imagineCacheManager) {
            $this->imagineCacheManager->remove([$currentFilepath], null);
        }
        if ($filesystem->fileExists($currentFilepath)) {
            $filesystem->delete($currentFilepath);
        }
    }

    private function copyFilepath(object $object, UploadableField $fieldConfiguration): ?string
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        if (!$filesystem->fileExists($currentFilepath)) {
            return null;
        }
        $pathInfo = pathinfo($currentFilepath);
        $basename = $pathInfo['filename'];
        $extension = $pathInfo['extension'] ?? null;
        if (!empty($extension)) {
            $extension = sprintf('.%s', $extension);
        }
        $num = 1;
        while ($filesystem->fileExists($newFilepath = sprintf('%s_%d%s', $basename, $num, $extension))) {
            ++$num;
        }
        $filesystem->copy($currentFilepath, $newFilepath);

        return $newFilepath;
    }
}
