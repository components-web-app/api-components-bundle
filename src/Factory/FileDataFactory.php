<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Factory;

use ApiPlatform\Core\Api\IriConverterInterface;
use Liip\ImagineBundle\Binary\MimeTypeGuesserInterface;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\Helper\PathHelper;
use Liip\ImagineBundle\Imagine\Data\DataManager;
use Liip\ImagineBundle\Model\Binary;
use Liip\ImagineBundle\Model\FileBinary;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Dto\File\FileData;
use Silverback\ApiComponentBundle\Dto\File\ImageMetadata;
use Silverback\ApiComponentBundle\Entity\Component\FileInterface;
use Silverback\ApiComponentBundle\EventSubscriber\EntitySubscriber\FileInterfaceSubscriber;
use Silverback\ApiComponentBundle\Imagine\PathResolver;
use Silverback\ApiComponentBundle\Validator\ImagineSupportedFilePath;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class FileDataFactory implements ServiceSubscriberInterface
{
    private $router;
    private $iriConverter;
    private $container;
    private $fileInterfaceSubscriber;

    public function __construct(RouterInterface $router, IriConverterInterface $iriConverter, ContainerInterface $container, FileInterfaceSubscriber $fileInterfaceSubscriber)
    {
        $this->router = $router;
        $this->iriConverter = $iriConverter;
        $this->container = $container;
        $this->fileInterfaceSubscriber = $fileInterfaceSubscriber;
    }

    public static function getSubscribedServices(): array
    {
        return [
            '?' . PathResolver::class,
            '?' . DataManager::class,
            '?' . CacheManager::class,
            '?' . MimeTypeGuesserInterface::class,
            FilterService::class
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
        if (ImagineSupportedFilePath::isValidFilePath($filePath)) {
            $this->fileInterfaceSubscriber->createFilteredImages($file);
        }

        $publicPath = $this->getPublicPath($file);
        $imageData = null;
        if ($this->fileIsImage($filePath)) {
//            /** @var MimeTypeGuesserInterface $mimeTypeGuesser */
//            $mimeTypeGuesser = $this->container->get(MimeTypeGuesserInterface::class);
//            $mimeType = $mimeTypeGuesser->guess($filePath);
            $imageData = new ImageMetadata($filePath, $publicPath);
        }

        return new FileData(
            $publicPath,
            $imageData,
            $this->getImagineData($file),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath) ?: null
        );
    }

    private function fileIsImage($filePath): bool
    {
        return \exif_imagetype($filePath) || mime_content_type($filePath) === 'image/svg+xml';
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
        /** @var DataManager $dataManager */
        $dataManager = $this->container->get(DataManager::class);
        /** @var FilterService $filterService */
        $filterService = $this->container->get(FilterService::class);


        foreach ($file::getImagineFilters() as $returnKey => $filter) {
            // Strip path root from beginning of string.
            // Whatever image roots are set in imagine will be looped and removed from the start of the string
            $resolvedPath = $pathResolver->resolve($filePath);
            $path = PathHelper::urlPathToFilePath($resolvedPath);
            $imagineBrowserPath = $filterService->getUrlOfFilteredImage($resolvedPath, $filter);
//            $imagineBrowserPath = $cacheManager->generateUrl($resolvedPath, $filter);

//            $imagineFilePath = urldecode(ltrim(
//                parse_url(
//                    $imagineBrowserPath,
//                    PHP_URL_PATH
//                ),
//                '/'
//            ));


            $imagineData[$returnKey] = new ImageMetadata($filePath, $imagineBrowserPath, $filter);
        }
        return $imagineData;
    }
}
