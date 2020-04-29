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
use Silverback\ApiComponentBundle\Annotation\File;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Utility\ClassMetadataTrait;
use Symfony\Component\HttpFoundation\FileBag;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class FileHelper extends AbstractHelper
{
    use ClassMetadataTrait;

    public function __construct(Reader $reader, ManagerRegistry $registry)
    {
        $this->reader = $reader;
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

    public function uploadFile(object $resource, FileBag $fileBag)
    {
        if (!$this->isConfigured($resource)) {
            throw new InvalidArgumentException('%s is not configured as a File');
        }
        $configuration = $this->getConfiguration($resource);
        $classMetadata = $this->getClassMetadata($resource);
        $uploadedFile = $fileBag->get($configuration->fileFieldName);
        $classMetadata->setFieldValue($resource, $configuration->filePathFieldName, '/uploaded_file_path');

        return $resource;
    }
}
