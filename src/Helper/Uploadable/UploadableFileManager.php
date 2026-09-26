<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

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
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    /**
     * @var \WeakMap<object, list<string>>
     */
    private \WeakMap $deletedFields;

    public function __construct(
        ManagerRegistry $registry,
        UploadableAttributeReader $annotationReader,
        FilesystemProvider $filesystemProvider,
        FlysystemDataLoader $flysystemDataLoader,
        FileInfoCacheManager $fileInfoCacheManager,
        ?CacheManager $imagineCacheManager,
        ?FilterService $filterService = null,
    ) {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->filesystemProvider = $filesystemProvider;
        $this->flysystemDataLoader = $flysystemDataLoader;
        $this->fileInfoCacheManager = $fileInfoCacheManager;
        $this->imagineCacheManager = $imagineCacheManager;
        $this->filterService = $filterService;
        $this->deletedFields = new \WeakMap();
    }

    public function addDeletedField(object $object, string $field): void
    {
        $fields = $this->deletedFields[$object] ?? [];
        if (!\in_array($field, $fields, true)) {
            $fields[] = $field;
        }
        $this->deletedFields[$object] = $fields;
    }

    public function transferDeletedFields(object $from, object $to): void
    {
        foreach ($this->deletedFields[$from] ?? [] as $field) {
            $this->addDeletedField($to, $field);
        }
    }

    private function isFieldDeleted(object $object, string $field): bool
    {
        return \in_array($field, $this->deletedFields[$object] ?? [], true);
    }

    public function processClonedUploadable(object $oldObject, object $newObject): object
    {
        if (!$this->annotationReader->isConfigured($oldObject)) {
            throw new \InvalidArgumentException('The old object is not configured as uploadable');
        }

        if ($oldObject::class !== $newObject::class) {
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
            $this->flysystemDataLoader->setAdapter($fieldConfiguration->adapter);

            $filename = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($filename && $object instanceof ImagineFiltersInterface && $this->filterService) {
                $mimeType = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter)->mimeType($filename);
                if (!str_contains($mimeType, 'image/') || 'image/svg+xml' === $mimeType) {
                    continue;
                }
                $filters = $object->getImagineFilters($fileProperty, null);
                foreach ($filters as $filter) {
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
            /** @var File|UploadedDataUriFile|null $file */
            $file = $propertyAccessor->getValue($object, $fileProperty);
            if (!$file) {
                if ($this->isFieldDeleted($object, $fieldConfiguration->property)) {
                    $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
                    $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
                }
                continue;
            }

            $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);

            $prefix = $fieldConfiguration->prefix ?? '';
            $tokenise = !$file instanceof UploadedDataUriFile;
            do {
                $path = $prefix . $this->generateStoredFilename($file);
            } while ($tokenise && $filesystem->fileExists($path));

            $stream = fopen($file->getRealPath(), 'r');
            $filesystem->writeStream(
                $path,
                $stream,
                [
                    'mimetype' => $file->getMimeType(),
                    'metadata' => [
                        'contentType' => $file->getMimeType(),
                    ],
                ]
            );
            $classMetadata->setFieldValue($object, $fieldConfiguration->property, $path);
            $propertyAccessor->setValue($object, $fileProperty, null);
        }
    }

    private function generateStoredFilename(File $file): string
    {
        if ($file instanceof UploadedDataUriFile) {
            return $file->getClientOriginalName();
        }

        return $this->tokeniseFilename($this->resolveOriginalName($file));
    }

    private function resolveOriginalName(File $file): string
    {
        $clientName = $file instanceof UploadedFile ? $file->getClientOriginalName() : '';
        $basename = $file->getFilename();

        if ('' !== pathinfo($clientName, \PATHINFO_EXTENSION)) {
            return $clientName;
        }
        if ('' !== pathinfo($basename, \PATHINFO_EXTENSION)) {
            return $basename;
        }

        return '' !== $clientName ? $clientName : $basename;
    }

    private function tokeniseFilename(string $originalName): string
    {
        $stem = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', pathinfo($originalName, \PATHINFO_FILENAME)));
        $stem = trim($stem, '-');
        if ('' === $stem) {
            $stem = 'file';
        }
        $stem = substr($stem, 0, 100);

        $name = $stem . '-' . bin2hex(random_bytes(4));

        $extension = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '', pathinfo($originalName, \PATHINFO_EXTENSION)));
        if ('' !== $extension) {
            $name .= '.' . $extension;
        }

        return $name;
    }

    public function deleteFiles(object $object): void
    {
        if (!$this->annotationReader->isConfigured($object)) {
            throw new \InvalidArgumentException('The object passed to delete files is not configured');
        }

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

    /**
     * @return array<string, string|null>
     */
    public function getStoredFilePaths(object $object): array
    {
        if (!$this->annotationReader->isConfigured($object)) {
            return [];
        }

        $classMetadata = $this->getClassMetadata($object);

        $paths = [];
        foreach ($this->annotationReader->getConfiguredProperties($object, true) as $fieldConfiguration) {
            $paths[$fieldConfiguration->property] = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        }

        return $paths;
    }

    /**
     * @param array<string, string|null> $previousPaths
     */
    public function deleteOrphanedFiles(object $object, array $previousPaths): void
    {
        if (!$this->annotationReader->isConfigured($object)) {
            return;
        }

        $classMetadata = $this->getClassMetadata($object);

        foreach ($this->annotationReader->getConfiguredProperties($object, true) as $fieldConfiguration) {
            $previousFilepath = $previousPaths[$fieldConfiguration->property] ?? null;
            if (!$previousFilepath || $previousFilepath === $classMetadata->getFieldValue($object, $fieldConfiguration->property)) {
                continue;
            }
            $this->deleteStoredFile($fieldConfiguration, $previousFilepath);
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
            throw new NotFoundHttpException(\sprintf('field configuration not found for %s', $property));
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
        $currentFilepath = $this->getClassMetadata($object)->getFieldValue($object, $fieldConfiguration->property);

        $this->deleteStoredFile($fieldConfiguration, $currentFilepath);
    }

    public function deleteStoredFile(UploadableField $fieldConfiguration, string $filepath): void
    {
        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $this->fileInfoCacheManager->deleteCaches([$filepath], [null]);
        if ($this->imagineCacheManager) {
            $this->imagineCacheManager->remove([$filepath], null);
        }
        if ($filesystem->fileExists($filepath)) {
            $filesystem->delete($filepath);
        }
    }

    private function copyFilepath(object $object, UploadableField $fieldConfiguration): ?string
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        if (!$filesystem->fileExists($currentFilepath)) {
            return $currentFilepath;
        }

        $pathInfo = pathinfo($currentFilepath);
        $directory = '.' === $pathInfo['dirname'] ? '' : $pathInfo['dirname'] . '/';

        $stem = (string) preg_replace('/-[0-9a-f]{8}$/', '', $pathInfo['filename']);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        do {
            $newFilepath = $directory . $this->tokeniseFilename($stem . $extension);
        } while ($filesystem->fileExists($newFilepath));

        $filesystem->copy($currentFilepath, $newFilepath);

        return $newFilepath;
    }
}
