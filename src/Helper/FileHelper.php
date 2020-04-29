<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Helper;

use Doctrine\Common\Annotations\Reader;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use Silverback\ApiComponentBundle\Annotation\File;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class FileHelper extends AbstractHelper
{
    use ClassMetadataTrait;

    private FilesystemProvider $filesystemProvider;

    public function __construct(Reader $reader, ManagerRegistry $registry, FilesystemProvider $filesystemProvider)
    {
        $this->filesystemProvider = $filesystemProvider;
        $this->initRegistry($registry);
        $this->initReader($reader);
    }

    /**
     * @param object|string $class
     */
    public function getConfiguration($class): File
    {
        return $this->getAnnotationConfiguration($class, File::class);
    }

    public function setUploadedFile(object $resource, FileBag $fileBag)
    {
        if (!$this->isConfigured($resource)) {
            throw new InvalidArgumentException('%s is not configured as a File');
        }
        $configuration = $this->getConfiguration($resource);
        $fileField = $configuration->fileFieldName;
        $uploadedFile = $fileBag->get($fileField);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $propertyAccessor->setValue($resource, $fileField, $uploadedFile);

        $classMetadata = $this->getClassMetadata($resource);

        // This is set now so that we will always trigger the doctrine lifecycle events to later persist this file to a filesystem
        $classMetadata->setFieldValue($resource, $configuration->uploadedAtFieldName, new \DateTime());

        return $resource;
    }

    public function persistUploadedFile(object $resource, ?array $entityChangeSet = null): void
    {
        if (!$this->isConfigured($resource)) {
            throw new InvalidArgumentException('%s is not configured as a File');
        }
        $configuration = $this->getConfiguration($resource);

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $file = $propertyAccessor->getValue($resource, $configuration->fileFieldName);
        if (!$file) {
            return;
        }

        $filesystem = $this->getFilesystem('local');

        $stream = fopen($file->getRealPath(), 'r');

        // Need to resolve the path
        $path = 'test_file';

        $filesystem->writeStream($path, $stream, [
            'mimetype' => $file->getMimeType(),
        ]);

        $classMetadata = $this->getClassMetadata($resource);
        $classMetadata->setFieldValue($resource, $configuration->filePathFieldName, $path);
    }

    public function removeFile(object $resource): void
    {
        $fs = $this->getFilesystem('local');

        // Need to resolve the path
        $path = 'test_file';

        $fs->delete($path);
    }

    private function getFilesystem($adapterName): Filesystem
    {
        $flysystemAdapter = $this->filesystemProvider->getAdapter($adapterName);

        return new Filesystem($flysystemAdapter);
    }
}
