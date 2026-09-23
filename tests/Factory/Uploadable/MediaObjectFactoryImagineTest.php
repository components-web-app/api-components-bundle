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
use Silverback\ApiComponentsBundle\Imagine\FlysystemDataLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\UrlHelper;

class MediaObjectFactoryImagineTest extends TestCase
{
    private const string FILE_PATH = 'uploads/test-file.png';

    public function test_a_filter_whose_source_image_cannot_be_loaded_is_skipped_and_logged(): void
    {
        $missing = new NotLoadableException('Source image "uploads/test-file.png" not found.');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(
            static fn (string $path, string $filter): string => 'thumbnail' === $filter ? throw $missing : '/media/cache/' . $filter . '/' . $path
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
        $filterService->method('getUrlOfFilteredImage')->willThrowException(new NotLoadableException('not found'));

        $mediaObjects = $this->buildFactory($filterService, null)->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(1, $mediaObjects);
        self::assertNull($mediaObjects[0]->imagineFilter);
    }

    public function test_every_loadable_filter_gets_a_media_object_populated_from_its_cached_file_info(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static fn (string $path, string $filter): string => '/media/cache/' . $filter . '/' . $path);

        $fileInfoCacheManager = $this->createStub(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturnCallback(
            static fn (string $path, ?string $filter = null): ?FileInfo => match ($filter) {
                null => new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100),
                'square' => new FileInfo(self::FILE_PATH, 'image/png', 512, 50, 50, 'square'),
                default => null,
            }
        );

        $dataLoader = $this->createMock(FlysystemDataLoader::class);
        $dataLoader->expects(self::once())->method('setAdapter')->with('test_adapter');

        $mediaObjects = $this->buildFactory($filterService, null, $fileInfoCacheManager, $dataLoader)->createMediaObjects(new \stdClass())->get('file');

        self::assertCount(3, $mediaObjects);
        self::assertSame('thumbnail', $mediaObjects[1]->imagineFilter);
        self::assertSame(-1, $mediaObjects[1]->width);
        self::assertSame('', $mediaObjects[1]->mimeType);
        self::assertSame('square', $mediaObjects[2]->imagineFilter);
        self::assertSame(50, $mediaObjects[2]->width);
        self::assertSame('image/png', $mediaObjects[2]->mimeType);
    }

    private function buildFactory(FilterService $filterService, ?LoggerInterface $logger, ?FileInfoCacheManager $fileInfoCacheManager = null, ?FlysystemDataLoader $dataLoader = null): MediaObjectFactory
    {
        $fieldConfig = new UploadableField(adapter: 'test_adapter', imagineFilters: ['thumbnail', 'square']);
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
            $fileInfoCacheManager->method('resolveCache')->willReturn(new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100));
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
            $filterService,
            $logger,
        );
    }
}
