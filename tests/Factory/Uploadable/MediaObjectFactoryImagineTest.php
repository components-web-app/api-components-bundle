<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\Uploadable;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Imagine\Cache\SignerInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Factory\Uploadable\ApiUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableCacheControl;
use Silverback\ApiComponentsBundle\Imagine\CacheManager as SilverbackCacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemCacheResolver;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\RouterInterface;

class MediaObjectFactoryImagineTest extends TestCase
{
    private const string FILE_PATH = 'uploads/test-file.png';

    public function test_a_filter_whose_source_image_cannot_be_loaded_is_skipped_and_logged(): void
    {
        $missing = new NotLoadableException('Source image "uploads/test-file.png" not found.');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(
            static fn (string $path, string $filter): bool => 'thumbnail' === $filter ? throw $missing : true
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('thumbnail'), self::callback(static fn (array $context): bool => 'thumbnail' === $context['filter'] && self::FILE_PATH === $context['path'] && $missing === $context['exception']));

        $mediaObjects = $this->buildFactory($filterService, $logger)->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(2, $mediaObjects);
        self::assertNull($mediaObjects[0]->imagineFilter);
        self::assertSame('square', $mediaObjects[1]->imagineFilter);
    }

    public function test_a_missing_source_image_is_skipped_without_a_logger(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willThrowException(new NotLoadableException('not found'));

        $mediaObjects = $this->buildFactory($filterService, null)->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(1, $mediaObjects);
        self::assertNull($mediaObjects[0]->imagineFilter);
    }

    public function test_every_loadable_filter_gets_a_media_object_populated_from_its_cached_file_info(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturn(true);

        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn([
            '' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
            'square' => new FileInfo(self::FILE_PATH, 'image/png', 512, 50, 50, 'square'),
        ]);

        $dataLoader = $this->createMock(FlysystemDataLoader::class);
        $dataLoader->expects(self::once())->method('setAdapter')->with('test_adapter');

        $mediaObjects = $this->buildFactory($filterService, null, $fileInfoCacheManager, $dataLoader, $this->resolvingFilterGenerator())->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(3, $mediaObjects);
        self::assertSame('thumbnail', $mediaObjects[1]->imagineFilter);
        self::assertSame(-1, $mediaObjects[1]->width);
        self::assertSame('', $mediaObjects[1]->mimeType);
        self::assertSame('square', $mediaObjects[2]->imagineFilter);
        self::assertSame(50, $mediaObjects[2]->width);
        self::assertSame('image/png', $mediaObjects[2]->mimeType);
    }

    public function test_the_original_and_every_variant_are_described_from_one_file_info_lookup_for_the_path(): void
    {
        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->expects(self::once())->method('resolveCaches')->with(self::FILE_PATH)->willReturn([
            '' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
            'thumbnail' => new FileInfo(self::FILE_PATH, 'image/webp', 256, 30, 30, 'thumbnail'),
            'square' => new FileInfo(self::FILE_PATH, 'image/png', 512, 50, 50, 'square'),
        ]);
        $fileInfoCacheManager->expects(self::never())->method('resolveCache');
        $fileInfoCacheManager->expects(self::never())->method('saveCache');

        $mediaObjects = $this->buildFactory($this->createStub(FilterService::class), null, $fileInfoCacheManager, null, $this->resolvingFilterGenerator())->createMediaObjects(new \stdClass())->get('file');

        self::assertSame([null, 'thumbnail', 'square'], array_map(static fn ($mediaObject): ?string => $mediaObject->imagineFilter, $mediaObjects));
        self::assertSame([1024, 256, 512], array_map(static fn ($mediaObject): ?int => $mediaObject->fileSize, $mediaObjects));
        self::assertSame(['image/png', 'image/webp', 'image/png'], array_map(static fn ($mediaObject): ?string => $mediaObject->mimeType, $mediaObjects));
        self::assertSame([100, 30, 50], array_map(static fn ($mediaObject): ?int => $mediaObject->width, $mediaObjects));
    }

    public function test_a_variant_missing_from_the_paths_file_info_is_looked_up_on_its_own_as_it_may_have_just_been_generated(): void
    {
        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn([
            '' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
            'square' => new FileInfo(self::FILE_PATH, 'image/png', 512, 50, 50, 'square'),
        ]);
        $fileInfoCacheManager->expects(self::once())->method('resolveCache')->with(self::FILE_PATH, 'thumbnail')
            ->willReturn(new FileInfo(self::FILE_PATH, 'image/webp', 256, 30, 30, 'thumbnail'));

        $mediaObjects = $this->buildFactory($this->createStub(FilterService::class), null, $fileInfoCacheManager, null, $this->resolvingFilterGenerator())->createMediaObjects(new \stdClass())->get('file');

        self::assertSame([1024, 256, 512], array_map(static fn ($mediaObject): ?int => $mediaObject->fileSize, $mediaObjects));
    }

    public function test_a_variant_generated_in_the_same_call_is_described_from_the_row_its_generation_wrote_not_the_one_read_before(): void
    {
        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn([
            '' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
            'thumbnail' => new FileInfo(self::FILE_PATH, 'image/png', 9999, 99, 99, 'thumbnail'),
            'square' => new FileInfo(self::FILE_PATH, 'image/png', 9999, 99, 99, 'square'),
        ]);
        $fileInfoCacheManager->expects(self::exactly(2))->method('resolveCache')->willReturnCallback(
            static fn (string $path, ?string $filter = null): FileInfo => new FileInfo(self::FILE_PATH, 'image/webp', 256, 30, 30, $filter)
        );
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturn(true);

        $mediaObjects = $this->buildFactory($filterService, null, $fileInfoCacheManager)->createMediaObjects(new \stdClass())->get('file');

        self::assertSame([1024, 256, 256], array_map(static fn ($mediaObject): ?int => $mediaObject->fileSize, $mediaObjects));
        self::assertSame(['image/png', 'image/webp', 'image/webp'], array_map(static fn ($mediaObject): ?string => $mediaObject->mimeType, $mediaObjects));
    }

    public function test_a_stored_variant_with_no_file_info_is_described_from_the_stored_file_and_its_file_info_saved(): void
    {
        $cache = new Filesystem(new InMemoryFilesystemAdapter());
        $variant = $this->png(30, 20);
        $cache->write('media/cache/thumbnail/' . self::FILE_PATH, $variant);
        $cache->write('media/cache/square/' . self::FILE_PATH, $this->png(10, 10));

        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn([
            '' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
            'square' => new FileInfo(self::FILE_PATH, 'image/png', 512, 50, 50, 'square'),
        ]);
        $fileInfoCacheManager->expects(self::once())->method('saveCache')->with(self::callback(
            static fn (FileInfo $fileInfo): bool => self::FILE_PATH === $fileInfo->path
                && 'thumbnail' === $fileInfo->filter
                && 'image/png' === $fileInfo->mimeType
                && \strlen($variant) === $fileInfo->fileSize
                && 30 === $fileInfo->width
                && 20 === $fileInfo->height
        ));

        $mediaObjects = $this->buildFactory($this->createStub(FilterService::class), null, $fileInfoCacheManager, null, $this->readableFilterGenerator($cache))->createMediaObjects(new \stdClass())->get('file');

        self::assertSame('thumbnail', $mediaObjects[1]->imagineFilter);
        self::assertSame(30, $mediaObjects[1]->width);
        self::assertSame(20, $mediaObjects[1]->height);
        self::assertSame(\strlen($variant), $mediaObjects[1]->fileSize);
        self::assertSame('image/png', $mediaObjects[1]->mimeType);
        self::assertSame(50, $mediaObjects[2]->width);
    }

    public function test_a_variant_with_no_file_info_that_cannot_be_read_has_unknown_dimensions(): void
    {
        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn(['' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100)]);
        $fileInfoCacheManager->expects(self::never())->method('saveCache');

        $mediaObjects = $this->buildFactory($this->createStub(FilterService::class), null, $fileInfoCacheManager, null, $this->readableFilterGenerator(new Filesystem(new InMemoryFilesystemAdapter())))->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(3, $mediaObjects);
        self::assertSame(-1, $mediaObjects[1]->width);
        self::assertSame(-1, $mediaObjects[1]->height);
        self::assertSame(-1, $mediaObjects[1]->fileSize);
        self::assertSame('', $mediaObjects[1]->mimeType);
    }

    public function test_a_stored_variant_with_no_file_info_that_is_not_an_image_has_unknown_dimensions(): void
    {
        $cache = new Filesystem(new InMemoryFilesystemAdapter());
        $cache->write('media/cache/thumbnail/' . self::FILE_PATH, 'not an image');

        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCaches')->willReturn(['' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100)]);
        $fileInfoCacheManager->expects(self::never())->method('saveCache');

        $mediaObjects = $this->buildFactory($this->createStub(FilterService::class), null, $fileInfoCacheManager, null, $this->readableFilterGenerator($cache))->createMediaObjects(new \stdClass())->get('file');

        self::assertSame(-1, $mediaObjects[1]->width);
        self::assertSame('', $mediaObjects[1]->mimeType);
    }

    public function test_variants_generated_on_read_use_the_fields_imagine_cache_control(): void
    {
        $cacheControl = new UploadableCacheControl(new FilesystemFactory(new ServiceLocator([])), null, 'public, max-age=2592000');
        $generated = [];
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(static function (string $path, string $filter) use ($cacheControl, &$generated): bool {
            $generated[$filter] = $cacheControl->forVariantOf($path);

            return true;
        });

        $this->buildFactory($filterService, null, field: new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail', 'square'], imagineCacheControl: 'public, max-age=600'), cacheControl: $cacheControl)->createMediaObjects(new \stdClass());

        self::assertSame(['thumbnail' => 'public, max-age=600', 'square' => 'public, max-age=600'], $generated);
    }

    private function resolvingFilterGenerator(): ImagineFilterGenerator
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturn(true);
        $cacheManager->method('resolve')->willReturnCallback(static fn (string $path, string $filter): string => '/media/cache/' . $filter . '/' . $path);

        return new ImagineFilterGenerator($this->createStub(FilterService::class), $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', null);
    }

    private function readableFilterGenerator(Filesystem $cache): ImagineFilterGenerator
    {
        $cacheManager = new SilverbackCacheManager(
            new FilterConfiguration(['thumbnail' => ['filters' => []], 'square' => ['filters' => []]]),
            $this->createStub(RouterInterface::class),
            $this->createStub(SignerInterface::class),
            new EventDispatcher(),
        );
        $cacheManager->addResolver('default', new FlysystemCacheResolver($cache, '/'));

        return new ImagineFilterGenerator($this->createStub(FilterService::class), $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', null);
    }

    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function buildFactory(FilterService $filterService, ?LoggerInterface $logger, ?FileInfoCacheManager $fileInfoCacheManager = null, ?FlysystemDataLoader $dataLoader = null, ?ImagineFilterGenerator $filterGenerator = null, ?UploadableField $field = null, ?UploadableCacheControl $cacheControl = null): MediaObjectFactory
    {
        $fieldConfig = $field ?? new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail', 'square']);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn(self::FILE_PATH);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn(new Filesystem(new InMemoryFilesystemAdapter()));

        if (null === $fileInfoCacheManager) {
            $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
            $fileInfoCacheManager->method('resolveCaches')->willReturn(['' => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100)]);
        }

        $apiGenerator = $this->createStub(ApiUrlGenerator::class);
        $apiGenerator->method('generateUrl')->willReturn('http://example.com/api/download');

        return new MediaObjectFactory(
            $registry,
            $fileInfoCacheManager,
            $annotationReader,
            $filesystemProvider,
            $dataLoader ?? $this->createStub(FlysystemDataLoader::class),
            new RequestStack(),
            $this->createStub(FilesystemFactory::class),
            new UrlHelper(new RequestStack()),
            new ServiceLocator(['api' => static fn () => $apiGenerator]),
            $filterGenerator ?? $this->unstoredFilterGenerator($filterService, $logger),
            $cacheControl,
        );
    }

    private function unstoredFilterGenerator(FilterService $filterService, ?LoggerInterface $logger): ImagineFilterGenerator
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturn(false);
        $cacheManager->method('resolve')->willReturnCallback(static fn (string $path, string $filter): string => '/media/cache/' . $filter . '/' . $path);

        return new ImagineFilterGenerator($filterService, $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', null, $logger);
    }
}
