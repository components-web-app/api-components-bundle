<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory;

use ApiPlatform\Core\Api\IriConverterInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Dto\File\FileData;
use Silverback\ApiComponentBundle\Dto\File\ImageMetadata;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Validator\ImagineSupportedFilePath;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;

class FileDataFactory implements ServiceSubscriberInterface
{
    private $router;
    private $iriConverter;
    private $container;
    private $projectDir;

    public function __construct(RouterInterface $router, IriConverterInterface $iriConverter, ContainerInterface $container, string $projectDir)
    {
        $this->router = $router;
        $this->iriConverter = $iriConverter;
        $this->container = $container;
        $this->projectDir = $projectDir;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . PathResolver::class,
            '?' . CacheManager::class
        ];
    }

    public function create(FileInterface $file): ?FileData
    {
        if (!($filePath = $file->getFilePath()) || !file_exists($filePath)) {
            return null;
        }
        if ($file->getFileData()) {
            return $file->getFileData();
        }

        $publicPath = $this->getPublicPath($file);
        $imageData = \exif_imagetype($filePath) ? new ImageMetadata($filePath, $publicPath) : null;

        return new FileData($publicPath, $imageData, $this->getImagineData($file));
    }

    private function getPublicPath(FileInterface $file): string
    {
        $objectId = $this->iriConverter->getIriFromItem($file);
        return $this->router->generate(
            'files_upload',
            ['field' => 'filePath', 'id' => $objectId]
        );
    }

    private function getImagineData(FileInterface $file): ?array
    {
        $filePath = $file->getFilePath();
        if (!class_exists(CacheManager::class) || !ImagineSupportedFilePath::isValidFilePath($filePath)) {
            return null;
        }
        $imagineData = [];
        /** @var PathResolver $pathResolver */
        $pathResolver = $this->container->get(PathResolver::class);
        /** @var CacheManager $cacheManager */
        $cacheManager = $this->container->get(CacheManager::class);
        foreach ($file::getImagineFilters() as $returnKey => $filter) {
            // Strip path root from beginning of string.
            // Whatever image roots are set in imagine will be looped and removed from the start of the string
            $resolvedPath = $pathResolver->resolve($filePath);
            $imagineBrowserPath = $cacheManager->getBrowserPath($resolvedPath, $filter);
            $imagineFilePath = ltrim(
                parse_url(
                    $imagineBrowserPath,
                    PHP_URL_PATH
                ),
                '/'
            );
            $realPath = sprintf('%s/public/%s', $this->projectDir, $imagineFilePath);
            $imagineData[$returnKey] = new ImageMetadata($realPath, $imagineFilePath, $filter);
        }
        return $imagineData;
    }
}
