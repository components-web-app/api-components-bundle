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

namespace Silverback\ApiComponentsBundle\Factory\Uploadable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToReadFile;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;
use Silverback\ApiComponentsBundle\Uploadable\FileInfoCacheHelper;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MediaObjectFactory
{
    use ClassMetadataTrait;

    private FileInfoCacheHelper $fileInfoCacheHelper;
    private UploadableAnnotationReader $annotationReader;
    private FilesystemProvider $filesystemProvider;
    private FlysystemDataLoader $flysystemDataLoader;
    private RequestStack $requestStack;
    private ?FilterService $filterService;

    public function __construct(
        ManagerRegistry $managerRegistry,
        FileInfoCacheHelper $fileInfoCacheHelper,
        UploadableAnnotationReader $annotationReader,
        FilesystemProvider $filesystemProvider,
        FlysystemDataLoader $flysystemDataLoader,
        RequestStack $requestStack,
        ?FilterService $filterService = null
    ) {
        $this->initRegistry($managerRegistry);
        $this->fileInfoCacheHelper = $fileInfoCacheHelper;
        $this->annotationReader = $annotationReader;
        $this->filesystemProvider = $filesystemProvider;
        $this->flysystemDataLoader = $flysystemDataLoader;
        $this->requestStack = $requestStack;
        $this->filterService = $filterService;
    }

    public function createMediaObjects(object $object): ?ArrayCollection
    {
        $collection = new ArrayCollection();
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $propertyMediaObjects = [];
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
            $path = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if (!$path) {
                continue;
            }
            if (!$filesystem->fileExists($path)) {
                continue;
            }

            // Populate the primary MediaObject
            try {
                $propertyMediaObjects[] = $this->create($filesystem, $path);
            } catch (UnableToReadFile $exception) {
            }

            array_push($propertyMediaObjects, ...$this->getMediaObjectsForImagineFilters($object, $path, $fieldConfiguration, $fileProperty));

            $collection->set($fieldConfiguration->property, $propertyMediaObjects);
        }

        return $collection->count() ? $collection : null;
    }

    /**
     * @return MediaObject[]
     */
    private function getMediaObjectsForImagineFilters(object $object, string $path, UploadableField $uploadableField, string $fileProperty): array
    {
        $mediaObjects = [];
        if (!$this->filterService) {
            return $mediaObjects;
        }

        // Let the data loader which should be configured for imagine to know which adapter to use
        $this->flysystemDataLoader->setAdapter($uploadableField->adapter);

        $filters = $uploadableField->imagineFilters;
        if ($object instanceof ImagineFiltersInterface) {
            $request = $this->requestStack->getMasterRequest();
            array_push($filters, ...$object->getImagineFilters($fileProperty, $request));
        }

        foreach ($filters as $filter) {
            $resolvedUrl = $this->filterService->getUrlOfFilteredImage($path, $filter);
            $mediaObjects[] = $this->createFromImagine($resolvedUrl, $path, $filter);
        }

        return $mediaObjects;
    }

    private function create(Filesystem $filesystem, string $filename): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = 'https://www.website.com/path';
        $mediaObject->imagineFilter = null;

        $fileInfo = $this->fileInfoCacheHelper->resolveCache($filename);
        if ($fileInfo) {
            return $this->populateMediaObjectFromCache($mediaObject, $fileInfo);
        }

        $mediaObject->fileSize = $filesystem->fileSize($filename);
        $mediaObject->mimeType = $filesystem->mimeType($filename);
        if (false !== strpos($mediaObject->mimeType, 'image/')) {
            $file = $filesystem->read($filename);
            if ('image/svg+xml' === $mediaObject->mimeType) {
                $xmlget = simplexml_load_string(file_get_contents($file));
                $xmlattributes = $xmlget->attributes();
                $mediaObject->width = (int) $xmlattributes->width;
                $mediaObject->height = (int) $xmlattributes->height;
            } else {
                [ $mediaObject->width, $mediaObject->height ] = @getimagesize($file);
            }
        }

        $fileInfo = new FileInfo($filename, $mediaObject->mimeType, $mediaObject->fileSize, $mediaObject->width, $mediaObject->height);
        $this->fileInfoCacheHelper->saveCache($fileInfo);

        return $mediaObject;
    }

    private function createFromImagine(string $contentUrl, string $path, string $imagineFilter): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = $contentUrl;
        $mediaObject->imagineFilter = $imagineFilter;

        $fileInfo = $this->fileInfoCacheHelper->resolveCache($path, $imagineFilter);
        if ($fileInfo) {
            return $this->populateMediaObjectFromCache($mediaObject, $fileInfo);
        }
        $mediaObject->width = $mediaObject->height = $mediaObject->fileSize = -1;
        $mediaObject->mimeType = '';

        return $mediaObject;
    }

    private function populateMediaObjectFromCache(MediaObject $mediaObject, FileInfo $fileInfo): MediaObject
    {
        $mediaObject->fileSize = $fileInfo->fileSize;
        $mediaObject->mimeType = $fileInfo->mimeType;
        $mediaObject->width = $fileInfo->width;
        $mediaObject->height = $fileInfo->height;

        return $mediaObject;
    }
}
