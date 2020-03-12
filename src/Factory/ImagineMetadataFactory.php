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

namespace Silverback\ApiComponentBundle\Factory;

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Silverback\ApiComponentBundle\Dto\File\ImageMetadata;
use Silverback\ApiComponentBundle\Dto\File\ImagineMetadata;
use Silverback\ApiComponentBundle\Entity\Utility\FileInterface;
use Silverback\ApiComponentBundle\Imagine\PathResolver;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ImagineMetadataFactory
{
    private CacheManager $cacheManager;
    private PathResolver $pathResolver;
    private string $projectDirectory;

    public function __construct(CacheManager $cacheManager, PathResolver $pathResolver, string $projectDirectory)
    {
        $this->cacheManager = $cacheManager;
        $this->pathResolver = $pathResolver;
        $this->projectDirectory = $projectDirectory;
    }

    public static function isImagineFilePath(?string $filePath): bool
    {
        if (!$filePath || !file_exists($filePath)) {
            return false;
        }
        try {
            $imageType = exif_imagetype($filePath);
        } catch (\Exception $e) {
            return false;
        }

        return \in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_JPEG2000, IMAGETYPE_PNG, IMAGETYPE_GIF], true);
    }

    public function create(FileInterface $file): ?ImagineMetadata
    {
        $filePath = $file->getFilePath();
        if (!class_exists(CacheManager::class) || !self::isImagineFilePath($filePath)) {
            return null;
        }
        $imagineMetadata = new ImagineMetadata();
        foreach ($file::getImagineFilters() as $returnKey => $filter) {
            $resolvedPath = $this->pathResolver->resolve($filePath);
            $imagineBrowserPath = $this->cacheManager->getBrowserPath($resolvedPath, $filter);
            $imagineFilePath = urldecode(ltrim(
                parse_url(
                    $imagineBrowserPath,
                    PHP_URL_PATH
                ),
                '/'
            ));
            $realPath = sprintf('%s/public/%s', $this->projectDirectory, $imagineFilePath);
            $imagineMetadata->addFilter($returnKey, new ImageMetadata($realPath, $imagineFilePath, $filter));
        }

        return $imagineMetadata;
    }
}
