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
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\UrlGeneration\PublicUrlGenerator as FlysystemPublicUrlGenerator;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator as FlysystemTemporaryUrlGenerator;
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

class MediaObjectFactoryUrlGeneratorTest extends TestCase
{
    private const FILE_PATH = 'uploads/test-file.png';

    private function buildFactory(string $urlGeneratorReference, object $adapter, Filesystem $filesystem): MediaObjectFactory
    {
        $fieldConfig = new UploadableField(adapter: 'test_adapter', urlGenerator: $urlGeneratorReference);
        $fieldConfig->property = 'filename';

        $annotationReader = $this->createMock(UploadableAttributeReaderInterface::class);
        $annotationReader->method('getConfiguredProperties')->willReturn(['file' => $fieldConfig]);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn(self::FILE_PATH);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($em);

        $filesystemProvider = $this->createMock(FilesystemProvider::class);
        $filesystemProvider->method('getFilesystem')->willReturn($filesystem);

        $filesystemFactory = $this->createMock(FilesystemFactory::class);
        $filesystemFactory->method('getAdapter')->willReturn($adapter);

        $fileInfo = new FileInfo(self::FILE_PATH, 'image/png', 1024, 100, 100);
        $fileInfoCacheManager = $this->createMock(FileInfoCacheManager::class);
        $fileInfoCacheManager->method('resolveCache')->willReturn($fileInfo);

        $apiGenerator = $this->createMock(ApiUrlGenerator::class);
        $apiGenerator->method('generateUrl')->willReturn('http://example.com/api/download');

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
            $this->createStub(FlysystemDataLoader::class),
            new RequestStack(),
            $filesystemFactory,
            new UrlHelper(new RequestStack()),
            $urlGenerators,
        );
    }

    public function test_uses_public_url_when_adapter_supports_public_url_generator(): void
    {
        $adapter = new class implements FlysystemPublicUrlGenerator {
            public function publicUrl(string $path, Config $config): string
            {
                return 'https://cdn.example.com/' . $path;
            }
        };

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('publicUrl')->willReturn('https://cdn.example.com/' . self::FILE_PATH);

        $factory = $this->buildFactory('public', $adapter, $filesystem);
        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertSame('https://cdn.example.com/' . self::FILE_PATH, $collection->get('file')[0]->contentUrl);
    }

    public function test_falls_back_to_api_when_adapter_does_not_support_public_url_generator(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('publicUrl');

        $factory = $this->buildFactory('public', new \stdClass(), $filesystem);
        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertSame('http://example.com/api/download', $collection->get('file')[0]->contentUrl);
    }

    public function test_uses_temporary_url_when_adapter_supports_temporary_url_generator(): void
    {
        $adapter = new class implements FlysystemTemporaryUrlGenerator {
            public function temporaryUrl(string $path, \DateTimeInterface $expiresAt, Config $config): string
            {
                return 'https://s3.example.com/' . $path . '?signed=abc';
            }
        };

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('temporaryUrl')->willReturn('https://s3.example.com/' . self::FILE_PATH . '?signed=abc');

        $factory = $this->buildFactory('temporary', $adapter, $filesystem);
        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertStringStartsWith('https://s3.example.com/', $collection->get('file')[0]->contentUrl);
        $this->assertStringContainsString('signed=abc', $collection->get('file')[0]->contentUrl);
    }

    public function test_falls_back_to_api_when_adapter_does_not_support_temporary_url_generator(): void
    {
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->never())->method('temporaryUrl');

        $factory = $this->buildFactory('temporary', new \stdClass(), $filesystem);
        $collection = $factory->createMediaObjects(new \stdClass());

        $this->assertSame('http://example.com/api/download', $collection->get('file')[0]->contentUrl);
    }
}
