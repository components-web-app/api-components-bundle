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

namespace Silverback\ApiComponentBundle\Factory\File;

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentBundle\Dto\File\FileData;
use Silverback\ApiComponentBundle\Dto\File\ImageMetadata;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FileDataFactory
{
    private IriConverterInterface $iriConverter;
    private RouterInterface $router;
    private ImagineMetadataFactory $imagineMetadataFactory;
    private UrlHelper $urlHelper;

    public function __construct(IriConverterInterface $iriConverter, RouterInterface $router, ImagineMetadataFactory $imagineMetadataFactory, UrlHelper $urlHelper)
    {
        $this->iriConverter = $iriConverter;
        $this->router = $router;
        $this->imagineMetadataFactory = $imagineMetadataFactory;
        $this->urlHelper = $urlHelper;
    }

    public function create(FileInterface $resource): ?FileData
    {
        if (!($filePath = $resource->getFilePath()) || !file_exists($filePath)) {
            return null;
        }

        if ($fileData = $resource->getFileData()) {
            return $fileData;
        }

        $publicPath = $this->getPublicPath($resource);

        $imageData = self::isImage($filePath) ? new ImageMetadata($filePath, $this->urlHelper->getAbsoluteUrl($publicPath)) : null;

        return new FileData(
            $this->urlHelper->getAbsoluteUrl($publicPath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath) ?: null,
            $imageData,
            $this->imagineMetadataFactory->create($resource)
        );
    }

    private static function isImage($filePath): bool
    {
        return exif_imagetype($filePath) || 'image/svg+xml' === mime_content_type($filePath);
    }

    private function getPublicPath(FileInterface $resource): string
    {
        $objectId = $this->iriConverter->getIriFromItem($resource);

        return $this->router->generate(
            'api_component_file_upload',
            ['field' => 'filePath', 'id' => $objectId]
        );
    }
}
