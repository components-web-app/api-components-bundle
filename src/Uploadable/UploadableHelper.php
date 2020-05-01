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

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AnnotationReader\UploadableAnnotationReader;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Model\Uploadable\UploadedBase64EncodedFile;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UploadableHelper
{
    use ClassMetadataTrait;

    private UploadableAnnotationReader $annotationReader;
    private FilesystemProvider $filesystemProvider;

    public function __construct(
        ManagerRegistry $registry,
        UploadableAnnotationReader $annotationReader,
        FilesystemProvider $filesystemProvider
    ) {
        $this->initRegistry($registry);
        $this->annotationReader = $annotationReader;
        $this->filesystemProvider = $filesystemProvider;
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

    public function persistFiles(object $object): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $classMetadata = $this->getClassMetadata($object);

        // $configuration = $this->annotationReader->getConfiguration($object);
        $configuredProperties = $this->annotationReader->getConfiguredProperties($object, true, true);
        /**
         * @var UploadableField[] $configuredProperties
         */
        foreach ($configuredProperties as $fileProperty => $fieldConfiguration) {
            $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
            if ($currentFilepath) {
                $this->removeFilepath($object, $fieldConfiguration);
            }
            /** @var UploadedBase64EncodedFile|null $file */
            $file = $propertyAccessor->getValue($object, $fileProperty);
            if (!$file) {
                $classMetadata->setFieldValue($object, $fieldConfiguration->property, null);
                continue;
            }

            $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);

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

    private function removeFilepath(object $object, UploadableField $fieldConfiguration): void
    {
        $classMetadata = $this->getClassMetadata($object);

        $filesystem = $this->filesystemProvider->getFilesystem($fieldConfiguration->adapter);
        $currentFilepath = $classMetadata->getFieldValue($object, $fieldConfiguration->property);
        $filesystem->delete($currentFilepath);
    }
}
