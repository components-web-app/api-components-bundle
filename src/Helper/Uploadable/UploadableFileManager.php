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
     * Storage properties the request payload explicitly cleared, keyed by the resource they were
     * cleared on. Keyed by object because the marker belongs to a resource, not to a property name:
     * `filename` is the default storage property for every UploadableField, so a marker held as a
     * bare name would apply to any resource written afterwards. A WeakMap rather than a plain map so
     * entries die with the objects — nothing accumulates on this shared service between requests
     * under a long-running runtime (FrankenPHP worker mode, RoadRunner), where a leaked marker would
     * silently delete the file of every later write that carries no new file, a publish being one.
     *
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

    /**
     * Hands a resource's deleted-field markers to the resource replacing it. Publishing a draft
     * merges it into the published resource and continues the write against that instance, so a
     * request that clears a file and publishes in one go must carry the marker across with it.
     */
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
            // Let the data loader which should be configured for imagine to know which adapter to use
            $this->flysystemDataLoader->setAdapter($fieldConfiguration->adapter);

            $filename = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($filename && $object instanceof ImagineFiltersInterface && $this->filterService) {
                // Only warm imagine caches for raster images (never SVG or non-image files such as a
                // PDF/docx) — Liip Imagine cannot process a non-image and would throw.
                $mimeType = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter)->mimeType($filename);
                if (!str_contains($mimeType, 'image/') || 'image/svg+xml' === $mimeType) {
                    continue;
                }
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
                if ($this->isFieldDeleted($object, $fieldConfiguration->property)) {
                    $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
                    $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
                }
                continue;
            }

            // Delete this resource's own current file first, freeing its slot so a
            // replacement can reuse the name without being seen as a foreign collision.
            $this->deleteFileForField($object, $classMetadata, $fieldConfiguration);
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);

            $prefix = $fieldConfiguration->prefix ?? '';
            // Data-URI uploads carry no client filename and are given a unique UUID name at
            // denormalization — keep it. Every other upload (multipart, fixtures) is stored under
            // its original filename plus a unique token, so each resource owns its own file and can
            // never overwrite (or, on delete, remove) another resource's file. The fileExists guard
            // upholds that invariant even against an astronomically unlikely token clash.
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

    /**
     * The basename to store an uploaded file under (excluding the field prefix).
     *
     * Data-URI uploads keep their unique UUID name assigned at denormalization. Multipart uploads
     * use the client's original filename; fixtures use the source file's basename — both tokenised.
     */
    private function generateStoredFilename(File $file): string
    {
        if ($file instanceof UploadedDataUriFile) {
            return $file->getClientOriginalName();
        }

        return $this->tokeniseFilename($this->resolveOriginalName($file));
    }

    /**
     * The original filename to derive the stored name from. Prefers whichever candidate carries a
     * file extension: for real multipart uploads that's the client's original name (the on-disk file
     * is a temp name); in some upload/test contexts the client name is absent or is the form field,
     * and the file's own basename holds the real name + extension.
     */
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

    /**
     * Produces `<sanitised-stem>-<token>.<ext>` from an original filename. The stem is slugified and
     * length-capped (path separators / traversal stripped by pathinfo + the slug), and a random token
     * makes every stored name unique and unguessable.
     */
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

    /**
     * Copies a resource's stored file so a clone (a publishable draft) owns its own object. The copy
     * is written beside the original — the field's prefix is part of the stored path, so a copy built
     * from the basename alone would escape it — under the same tokenised naming as every other stored
     * file, which also guarantees it cannot collide with an existing object.
     */
    private function copyFilepath(object $object, UploadableField $fieldConfiguration): ?string
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        if (!$filesystem->fileExists($currentFilepath)) {
            // The stored object is gone. Keep the clone pointing at the same path rather than nulling
            // it: a missing file is a recoverable storage problem, whereas nulling discards the only
            // record of what the file was and, on the next publish, copies that null over the
            // published resource's own path.
            return $currentFilepath;
        }

        $pathInfo = pathinfo($currentFilepath);
        $directory = '.' === $pathInfo['dirname'] ? '' : $pathInfo['dirname'] . '/';

        // Strip the token off an already-tokenised name before adding a new one, so a resource that
        // is drafted and published repeatedly does not accumulate a token per cycle.
        $stem = (string) preg_replace('/-[0-9a-f]{8}$/', '', $pathInfo['filename']);
        $extension = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';

        do {
            $newFilepath = $directory . $this->tokeniseFilename($stem . $extension);
        } while ($filesystem->fileExists($newFilepath));

        $filesystem->copy($currentFilepath, $newFilepath);

        return $newFilepath;
    }
}
