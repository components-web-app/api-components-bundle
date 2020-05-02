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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use League\Flysystem\Filesystem;
use Silverback\ApiComponentsBundle\Imagine\Entity\ImagineCachedFileMetadata;
use Silverback\ApiComponentsBundle\Model\Uploadable\MediaObject;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MediaObjectFactory
{
    private ObjectRepository $respository;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->respository = $entityManager->getRepository(ImagineCachedFileMetadata::class);
    }

    public function create(Filesystem $filesystem, string $filename, string $imagineFilter = null): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = 'https://www.website.com/path';
        $mediaObject->fileSize = $filesystem->fileSize($filename);
        $mediaObject->mimeType = $filesystem->mimeType($filename);
        $mediaObject->imagineFilter = $imagineFilter;

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

        return $mediaObject;
    }

    public function createFromImagine(string $contentUrl, string $path, string $imagineFilter): MediaObject
    {
        $mediaObject = new MediaObject();
        $mediaObject->contentUrl = $contentUrl;
        $mediaObject->imagineFilter = $imagineFilter;

        /** @var ImagineCachedFileMetadata|null $cachedFileMetadata */
        $cachedFileMetadata = $this->respository
            ->findOneBy(
                [
                    'filter' => $imagineFilter,
                    'path' => $path,
                ]
            );
        if ($cachedFileMetadata) {
            $mediaObject->fileSize = $cachedFileMetadata->fileSize;
            $mediaObject->mimeType = $cachedFileMetadata->mimeType;
            $mediaObject->width = $cachedFileMetadata->width;
            $mediaObject->height = $cachedFileMetadata->height;
        } else {
            $mediaObject->width = $mediaObject->height = $mediaObject->fileSize = -1;
            $mediaObject->mimeType = '';
        }

        return $mediaObject;
    }
}
