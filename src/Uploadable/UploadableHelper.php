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

namespace Silverback\ApiComponentsBundle\Uploadable;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\UnableToReadFile;
use Liip\ImagineBundle\Service\FilterService;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Entity\Utility\ImagineFiltersInterface;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedDataUriFile;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableHelper
{
    use ClassMetadataTrait;

    private UploadableAnnotationReader $annotationReader;
    private FilesystemProvider $filesystemProvider;
    private MediaObjectFactory $mediaObjectFactory;
    private RequestStack $requestStack;
    private ?FilterService $filterService;
    private FlysystemDataLoader $flysystemDataLoader;

    public function __construct(
        ManagerRegistry $registry,
        UploadableAnnotationReader $annotationReader,
        FilesystemProvider $filesystemProvider,
        MediaObjectFactory $mediaObjectFactory,
        RequestStack $requestStack,
        FlysystemDataLoader $flysystemDataLoader,
        ?FilterService $filterService = null
    ) {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->filesystemProvider = $filesystemProvider;
        $this->mediaObjectFactory = $mediaObjectFactory;
        $this->requestStack = $requestStack;
        $this->flysystemDataLoader = $flysystemDataLoader;
        $this->filterService = $filterService;
    }

    public function setUploadedFilesFromFileBag(object $object, FileBag $fileBag): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, false, true);

        /**
         * @var UploadableField[] $configuredProperties
         */
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            if ($file = $fileBag->get($fileProperty, null)) {
                $propertyAccessor->setValue($object, $fileProperty, $file);
            }
        }
    }

    public function storeFilesMetadata(object $object): void
    {
        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        $classMetadata = $this->getClassMetadata($object);

        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            // Let the data loader which should be configured for imagine to know which adapter to use
            $this->flysystemDataLoader->setAdapter($fieldConfiguration->adapter);

            $filename = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($object instanceof ImagineFiltersInterface && $this->filterService) {
                $filters = $object->getImagineFilters(null);
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

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        /**
         * @var UploadableField[] $configuredProperties
         */
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($currentFilepath) {
                $this->removeFilepath($object, $fieldConfiguration);
            }
            /** @var UploadedDataUriFile|null $file */
            $file = $propertyAccessor->getValue($object, $fileProperty);
            if (!$file) {
                $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
                continue;
            }

            $filesystem = $this->getFilesystemFromFieldConfiguration($fieldConfiguration);

            $path = $fieldConfiguration->prefix ?? '';
            $path .= $file->getFilename();
            $stream = fopen($file->getRealPath(), 'r');
            $filesystem->writeStream($path, $stream, [
                'mimetype' => $file->getMimeType(),
            ]);
            $classMetadata->setFieldValue($object, $fieldConfiguration->property, $path);
            $propertyAccessor->setValue($object, $fileProperty, null);
        }
    }

    public function deleteFiles(object $object): void
    {
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($currentFilepath) {
                $this->removeFilepath($object, $fieldConfiguration);
            }
        }
    }

    public function getMediaObjects(object $object): ?ArrayCollection
    {
        $collection = new ArrayCollection();
        $classMetadata = $this->getClassMetadata($object);

        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $propertyMediaObjects = [];
            $filesystem = $this->getFilesystemFromFieldConfiguration($fieldConfiguration);
            $path = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if (!$path) {
                continue;
            }
            if (!$filesystem->fileExists($path)) {
                continue;
            }

            // Populate the primary MediaObject
            try {
                $propertyMediaObjects[] = $this->mediaObjectFactory->create($filesystem, $path);
            } catch (UnableToReadFile $exception) {
            }

            if ($object instanceof ImagineFiltersInterface) {
                array_push($propertyMediaObjects, ...$this->getMediaObjectsForImagineFilters($object, $path, $fieldConfiguration->adapter));
            }

            $collection->set($fieldConfiguration->property, $propertyMediaObjects);
        }

        return $collection->count() ? $collection : null;
    }

    /**
     * @return MediaObject[]
     */
    private function getMediaObjectsForImagineFilters(ImagineFiltersInterface $object, string $path, string $adapter): array
    {
        // Let the data loader which should be configured for imagine to know which adapter to use
        $this->flysystemDataLoader->setAdapter($adapter);

        $mediaObjects = [];
        if (!$this->filterService) {
            return $mediaObjects;
        }

        $request = $this->requestStack->getMasterRequest();
        $filters = $object->getImagineFilters($request);
        foreach ($filters as $filter) {
            $resolvedUrl = $this->filterService->getUrlOfFilteredImage($path, $filter);
            $mediaObjects[] = $this->mediaObjectFactory->createFromImagine($resolvedUrl, $path, $filter);
        }

        return $mediaObjects;
    }

    private function getFilesystemFromFieldConfiguration(UploadableField $fieldConfiguration): Filesystem
    {
        return $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
    }

    private function removeFilepath(object $object, UploadableField $fieldConfiguration): void
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        if ($filesystem->fileExists($currentFilepath)) {
            $filesystem->delete($currentFilepath);
        }
    }
}
