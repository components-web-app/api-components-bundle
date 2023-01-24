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
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MediaObjectFactory
{
    use ClassMetadataTrait;

    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly FileInfoCacheManager $fileInfoCacheManager,
        private readonly UploadableAttributeReader $annotationReader,
        private readonly FilesystemProvider $filesystemProvider,
        private readonly FlysystemDataLoader $flysystemDataLoader,
        private readonly RequestStack $requestStack,
        private readonly ApiUrlGenerator $urlGenerator,
        private readonly ?FilterService $filterService = null)
    {
        $this->initRegistry($managerRegistry);
    }

    public function createMediaObjects(object $object): ?ArrayCollection
    {
        $collection = new ArrayCollection();
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true);

        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $propertyMediaObjects = [];
            // todo: we may need to look at the performance of this when getting the components. yes, the response is cached, but even first load on a page with lots of files, could be very bad
            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
            $path = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if (!$path) {
                continue;
            }
            if (!$filesystem->fileExists($path)) {
                continue;
            }

            // todo: the content URL perhaps will just be a public URL from the source/CDN instead of via this API download action
//            if ($filesystem instanceof PublicUrlGenerator) {
//                // $filesystem->publicUrl();
//            }
//            if ($filesystem instanceof TemporaryUrlGenerator) {
//                // $filesystem->temporaryUrl();
//            }
            $contentUrl = $this->urlGenerator->generateUrl($object, $fileProperty);

            // Populate the primary MediaObject
            try {
                $propertyMediaObjects[] = $this->create($filesystem, $path, $contentUrl);
            } catch (UnableToReadFile $exception) {
            }

            array_push($propertyMediaObjects, ...$this->getMediaObjectsForImagineFilters($object, $path, $fieldConfiguration, $fileProperty));

            $collection->set($fileProperty, $propertyMediaObjects);
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
            $request = $this->requestStack->getMainRequest();
            array_push($filters, ...$object->getImagineFilters($fileProperty, $request));
        }

        foreach ($filters as $filter) {
            $resolvedUrl = $this->filterService->getUrlOfFilteredImage($path, $filter);
            $mediaObjects[] = $this->createFromImagine($resolvedUrl, $path, $filter);
        }

        return $mediaObjects;
    }

    private function create(Filesystem $filesystem, string $filename, string $contentUrl): MediaObject
    {
        $mediaObject = new MediaObject();

        $mediaObject->contentUrl = $contentUrl;
        $mediaObject->imagineFilter = null;

        $fileInfo = $this->fileInfoCacheManager->resolveCache($filename);
        if ($fileInfo) {
            return $this->populateMediaObjectFromCache($mediaObject, $fileInfo);
        }

        $mediaObject->fileSize = $filesystem->fileSize($filename);
        $mediaObject->mimeType = $filesystem->mimeType($filename);
        if (str_contains($mediaObject->mimeType, 'image/')) {
            $file = str_replace("\0", '', $filesystem->read($filename));
            if ('image/svg+xml' === $mediaObject->mimeType) {
                $xmlGet = simplexml_load_string($file);
                $xmlAttributes = $xmlGet->attributes();
                $mediaObject->width = $xmlAttributes ? (int) $xmlAttributes->width : null;
                $mediaObject->height = $xmlAttributes ? (int) $xmlAttributes->height : null;
            } else {
                [$mediaObject->width, $mediaObject->height] = @getimagesize($file);
            }
        }

        $fileInfo = new FileInfo($filename, $mediaObject->mimeType, $mediaObject->fileSize, $mediaObject->width, $mediaObject->height);
        $this->fileInfoCacheManager->saveCache($fileInfo);

        return $mediaObject;
    }

    private function createFromImagine(string $contentUrl, string $path, string $imagineFilter): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = $contentUrl;
        $mediaObject->imagineFilter = $imagineFilter;

        $fileInfo = $this->fileInfoCacheManager->resolveCache($path, $imagineFilter);
        if ($fileInfo) {
            return $this->populateMediaObjectFromCache($mediaObject, $fileInfo);
        }

        // todo: check why we are setting this, from imagine we should know this info I'm guessing
        // todo: should we not save the info to cache as well as above?
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
