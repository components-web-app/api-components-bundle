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
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReaderInterface;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Factory\Uploadable\ApiUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\MediaObjectFactory;
use Silverback\ApiComponentsBundle\Factory\Uploadable\PublicUrlGenerator;
use Silverback\ApiComponentsBundle\Factory\Uploadable\TemporaryUrlGenerator;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoCacheManager;
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;

#[\PHPUnit\Framework\Attributes\CoversClass(MediaObjectFactory::class)]
class MediaObjectFactoryTest extends TestCase
{
    private const FILE_PATH = 'uploads/test-file.png';

    private function buildFactory(
        ?UploadableAttributeReaderInterface $annotationReader = null,
        ?FileInfoCacheManager $fileInfoCacheManager = null,
        ?FilesystemProvider $filesystemProvider = null,
        ?Filesystem $filesystem = null,
        ?FlysystemDataLoader $flysystemDataLoader = null,
        ?FilterService $filterService = null,
        ?UrlHelper $urlHelper = null,
    ): MediaObjectFactory {
        $fieldConfig = new UploadableField(adapter: 'test_adapter');
        $fieldConfig->property = 'filename';

        if ($annotationReader === null) {
            $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
            $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);
        }

        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn(self::FILE_PATH);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        if ($filesystem === null) {
            $filesystem = $this->createStub(Filesystem::class);
            $filesystem->method('mimeType')->willReturn('image/png');
            $filesystem->method('fileSize')->willReturn(1024);
            $filesystem->method('read')->willReturn('PNG_DATA');
        }

        if ($filesystemProvider === null) {
            $filesystemProvider = $this->createStub(FilesystemProvider::class);
            $filesystemProvider->method('getFilesystem')->willReturn($filesystem);
        }

        $fileInfo = new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100);

        if ($fileInfoCacheManager === null) {
            $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
            $fileInfoCacheManager->method('resolveCache')->willReturn($fileInfo);
        }

        $apiGenerator = $this->createStub(ApiUrlGenerator::class);
        $apiGenerator->method('generateUrl')->willReturn('http://example.com/api/download/file');

        $urlGenerators = new ServiceLocator([
            'api' => static fn () => $apiGenerator,
            'public' => static fn () => new PublicUrlGenerator(),
            'temporary' => static fn () => new TemporaryUrlGenerator(),
        ]);

        return new MediaObjectFactory(
            $registry,
            $fileInfoCacheManager,
            $annotationReader,
            $filesystemProvider,
            $flysystemDataLoader ?? $this->createStub(FlysystemDataLoader::class),
            new RequestStack(),
            $this->createStub(FilesystemFactory::class),
            $urlHelper ?? new UrlHelper(new RequestStack()),
            $urlGenerators,
            $filterService,
        );
    }

    public function test_get_configured_properties_called_with_skip_uploadable_check_true(): void
    {
        // Mutant 36: changes true to false — verify the argument IS true
        $annotationReader = $this->createMock(UploadableAttributeReaderInterface::class);
        $annotationReader->expects($this->once())
            ->method('getConfiguredProperties')
            ->with($this->anything(), true)
            ->willReturn([]);

        $factory = $this->buildFactory(annotationReader: $annotationReader);
        $factory->createMediaObjects(new \stdClass());
    }

    public function test_non_svg_image_adds_imagine_filter_media_objects(): void
    {
        // Mutant 37: if(!isMediaObjectSvg) → if(isMediaObjectSvg) — for PNG, imagine filters must be added
        // Mutant 38: array_push removed — imagine filters not added without it
        $fieldConfig = new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail']);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('mimeType')->willReturn('image/png');
        $filesystem->method('fileSize')->willReturn(1024);
        $filesystem->method('read')->willReturn('PNG_DATA');

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $fileInfo = new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100);
        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn($fileInfo);

        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturn('/uploads/thumbnail__test-file.png');

        $factory = $this->buildFactory(
            annotationReader: $annotationReader,
            fileInfoCacheManager: $fileInfoCacheManager,
            filesystemProvider: $filesystemProvider,
            filesystem: $filesystem,
            filterService: $filterService,
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        // For a non-SVG PNG with a filter, the result must include the imagine-filter media object
        $this->assertNotNull($collection);
        $mediaObjects = $collection->get('file');
        $this->assertGreaterThan(1, \count($mediaObjects), 'PNG image must include additional imagine-filter media objects');

        $imagineMediaObject = $mediaObjects[1];
        $this->assertSame('thumbnail', $imagineMediaObject->imagineFilter);
    }

    public function test_svg_image_does_not_add_imagine_filter_media_objects(): void
    {
        // Mutant 37: if(!isMediaObjectSvg) → if(isMediaObjectSvg) — for SVG, no imagine filters should be added
        $fieldConfig = new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail']);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"></svg>';

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('mimeType')->willReturn('image/svg+xml');
        $filesystem->method('fileSize')->willReturn(\strlen($svgContent));
        $filesystem->method('read')->willReturn($svgContent);

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn(null);

        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->never())->method('getUrlOfFilteredImage');

        $factory = $this->buildFactory(
            annotationReader: $annotationReader,
            fileInfoCacheManager: $fileInfoCacheManager,
            filesystemProvider: $filesystemProvider,
            filesystem: $filesystem,
            filterService: $filterService,
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertNotNull($collection);
        $mediaObjects = $collection->get('file');
        $this->assertCount(1, $mediaObjects, 'SVG image must not add imagine-filter media objects');
    }

    public function test_no_filter_service_returns_only_primary_media_object(): void
    {
        // Mutants 39, 40: if(!$this->filterService) → if($this->filterService); return removed
        // Without filterService, getMediaObjectsForImagineFilters must return empty array
        $fieldConfig = new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail']);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $factory = $this->buildFactory(
            annotationReader: $annotationReader,
            filterService: null, // no filterService
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertNotNull($collection);
        $mediaObjects = $collection->get('file');
        $this->assertCount(1, $mediaObjects, 'Without filterService, only the primary media object must be returned');
        $this->assertNull($mediaObjects[0]->imagineFilter);
    }

    public function test_is_media_object_svg_returns_true_for_svg_mime_type(): void
    {
        // Mutant 42: 'image/svg+xml' === $mimeType → !== — SVG detection is inverted
        // We test indirectly: SVG image must NOT add imagine filters; PNG must add them
        // This is covered by the SVG/non-SVG tests above, but we also verify via mime type
        $fieldConfig = new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumb']);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createStub(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('mimeType')->willReturn('image/svg+xml');
        $filesystem->method('fileSize')->willReturn(\strlen($svgContent));
        $filesystem->method('read')->willReturn($svgContent);

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn(null);

        // filterService is present — if SVG detection is correct, it must NOT be called
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects($this->never())->method('getUrlOfFilteredImage');

        $factory = $this->buildFactory(
            annotationReader: $annotationReader,
            fileInfoCacheManager: $fileInfoCacheManager,
            filesystemProvider: $filesystemProvider,
            filesystem: $filesystem,
            filterService: $filterService,
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertNotNull($collection);
        $this->assertCount(1, $collection->get('file'));
    }

    public function test_cache_hit_populates_from_cache_without_filesystem_calls(): void
    {
        // Mutants 43, 44: if($fileInfo) negated or return removed — cache is not used
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('fileSize');
        $filesystem->expects($this->never())->method('mimeType');
        $filesystem->expects($this->never())->method('read');

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $fileInfo = new FileInfo(self::FILE_PATH, 'image/png', 999, 200, 150);
        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn($fileInfo);

        $factory = $this->buildFactory(
            fileInfoCacheManager: $fileInfoCacheManager,
            filesystemProvider: $filesystemProvider,
            filesystem: $filesystem,
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertNotNull($collection);
        $mediaObject = $collection->get('file')[0];

        // Data must come from cache, not filesystem
        $this->assertSame(999, $mediaObject->fileSize);
        $this->assertSame('image/png', $mediaObject->mimeType);
        $this->assertSame(200, $mediaObject->width);
        $this->assertSame(150, $mediaObject->height);
    }

    public function test_cache_miss_reads_from_filesystem(): void
    {
        // Complement to cache hit test — ensures the non-cache path also works
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('fileSize')->willReturn(2048);
        $filesystem->method('mimeType')->willReturn('application/pdf');

        $filesystemProvider = $this->createStub(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn(null);
        $fileInfoCacheManager->expects($this->once())->method('saveCache');

        $factory = $this->buildFactory(
            fileInfoCacheManager: $fileInfoCacheManager,
            filesystemProvider: $filesystemProvider,
            filesystem: $filesystem,
        );

        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertNotNull($collection);
        $mediaObject = $collection->get('file')[0];
        $this->assertSame(2048, $mediaObject->fileSize);
        $this->assertSame('application/pdf', $mediaObject->mimeType);
    }
}
