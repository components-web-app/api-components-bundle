<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Uploadable;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\ImagineVariantGenerator;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Silverback\ApiComponentsBundle\Repository\Core\FileInfoRepository;
use Silverback\ApiComponentsBundle\Tests\Imagine\MemoryLimitAboveUsageTrait;
use Symfony\Component\DependencyInjection\ServiceLocator;

trait ImagineVariantGeneratorTestTrait
{
    use MemoryLimitAboveUsageTrait;

    private Filesystem $local;
    private FlysystemDataLoader $dataLoader;
    /** @var array<string, true> */
    private array $stored = [];
    /** @var list<array{string, string, ?string}> */
    private array $generated = [];
    /** @var array<string, \Exception> */
    private array $failures = [];
    /** @var array<string, \Exception> */
    private array $storedCheckFailures = [];
    private string $originalMemoryLimit;
    private string $memoryCeiling;
    private UploadableCacheControl $cacheControl;
    /** @var array<string, ?string> */
    private array $cacheControlAtGeneration = [];
    private ?\Closure $afterGenerate = null;

    private function setUpImagine(): void
    {
        $this->originalMemoryLimit = (string) \ini_get('memory_limit');
        $this->memoryCeiling = self::megabytesAboveUsage(512);
        self::setMemoryLimit(self::megabytesAboveUsage(256));
        $this->local = new Filesystem(new InMemoryFilesystemAdapter());
    }

    private function tearDownImagine(): void
    {
        ini_set('memory_limit', $this->originalMemoryLimit);
    }

    private function imagineVariantGenerator(int $batchSize = 50, ?LoggerInterface $logger = null): ImagineVariantGenerator
    {
        $filesystemProvider = new FilesystemProvider(new ServiceLocator(['local' => fn () => $this->local]));
        $this->dataLoader = new FlysystemDataLoader($filesystemProvider);
        $this->cacheControl = new UploadableCacheControl(new FilesystemFactory(new ServiceLocator([])), null, 'public, max-age=2592000');

        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturnCallback(function (string $path, string $filter): bool {
            if (isset($this->storedCheckFailures[$filter])) {
                throw $this->storedCheckFailures[$filter];
            }

            return isset($this->stored[$filter . ':' . $path]);
        });
        $filterService = $this->createStub(FilterService::class);
        $cacheManager->method('resolve')->willReturnCallback(static fn (string $path, string $filter): string => '/media/cache/' . $filter . '/' . $path);
        $filterService->method('warmUpCache')->willReturnCallback(function (string $path, string $filter): bool {
            $this->generated[] = [$filter, $path, (new \ReflectionProperty(FlysystemDataLoader::class, 'adapter'))->getValue($this->dataLoader)];
            $this->cacheControlAtGeneration[$filter . ':' . $path] = $this->cacheControl->forVariantOf($path);
            if (isset($this->failures[$filter])) {
                throw $this->failures[$filter];
            }
            $this->stored[$filter . ':' . $path] = true;
            if ($this->afterGenerate) {
                ($this->afterGenerate)();
            }

            return true;
        });

        return new ImagineVariantGenerator(
            $this->registry,
            new UploadableAttributeReader($this->registry, true),
            $filesystemProvider,
            $this->dataLoader,
            new FileInfoCacheManager($this->entityManager, new FileInfoRepository($this->registry)),
            $this->cacheControl,
            new ImagineFilterGenerator($filterService, $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', $this->memoryCeiling, $logger),
            $batchSize,
            $logger,
        );
    }

    private function writeImage(string $path, int $width = 10, int $height = 10): void
    {
        $header = pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0);
        $this->local->write($path, "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $header . pack('N', crc32('IHDR' . $header)));
    }

    private function fileInfo(string $path, string $mimeType, ?int $width, ?int $height): void
    {
        $this->entityManager->persist(new FileInfo($path, $mimeType, 1, $width, $height));
        $this->entityManager->flush();
    }
}
