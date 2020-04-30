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
use Ramsey\Uuid\Uuid;
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

        $filesystem = $this->getFilesystem($configuration->adapter);

        $stream = fopen($file->getRealPath(), 'r');

        $path = Uuid::uuid4()->getHex()->toString();

        $filesystem->writeStream($path, $stream, [
            'mimetype' => $file->getMimeType(),
        ]);

        $classMetadata = $this->getClassMetadata($resource);
        $classMetadata->setFieldValue($resource, $configuration->filePathFieldName, $path);
    }

    public function removeFile(object $resource): void
    {
        $this->getFilesystem($this->getConfiguration($resource)->adapter)->delete(Uuid::uuid4()->getHex()->toString());
    }

    private function getFilesystem($adapterName): Filesystem
    {
        return new Filesystem($this->filesystemProvider->getAdapter($adapterName));
    }
}
