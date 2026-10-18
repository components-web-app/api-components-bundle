<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\After;
use Silverback\ApiComponentsBundle\AttributeReader\UploadableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\FileInfo;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Serializer\Normalizer\DataUriNormalizer;

trait UploadsTrait
{
    use ResourceTrait;

    #[After]
    protected function removeStoredFiles(): void
    {
        (new LocalFilesystem())->remove(static::uploadsDirectory());
    }

    protected static function assetPath(string $name = 'image.png'): string
    {
        return \dirname(__DIR__, 3) . '/features/assets/files/' . $name;
    }

    protected static function assetFile(string $name = 'image.png'): File
    {
        return new File(static::assetPath($name));
    }

    protected static function dataUriOf(string $name): string
    {
        return (new DataUriNormalizer())->normalize(new \SplFileObject(static::assetPath($name)));
    }

    protected static function base64Of(string $name): string
    {
        return base64_encode((string) file_get_contents(static::assetPath($name)));
    }

    /**
     * @template T of object
     *
     * @param T                     $resource
     * @param array<string, string> $files    the asset file to upload, by uploadable field
     *
     * @return T
     */
    protected static function createUploadable(object $resource, array $files = ['file' => 'image.png']): object
    {
        foreach ($files as $field => $name) {
            $resource->{$field} = static::assetFile($name);
        }
        static::getContainer()->get(UploadableFileManager::class)->persistFiles($resource);
        static::persistAndFlush($resource);

        return $resource;
    }

    protected static function filesystem(string $adapter = 'local'): Filesystem
    {
        return static::getContainer()->get(FilesystemProvider::class)->getFilesystem($adapter);
    }

    protected static function storedPathOf(object $resource): string
    {
        $filename = static::reloaded($resource)->getFilename();
        self::assertNotNull($filename, 'The resource has no stored file.');

        return $filename;
    }

    /**
     * @return list<array{0: string, 1: string}> the adapter and path of each stored file
     */
    protected static function storedFilesOf(object $resource): array
    {
        $resource = static::reloaded($resource);
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $files = [];
        foreach (static::getContainer()->get(UploadableAttributeReader::class)->getConfiguredProperties($resource, true) as $configuration) {
            $path = $propertyAccessor->getValue($resource, $configuration->property);
            if (!empty($path)) {
                $files[] = [$configuration->adapter, $path];
            }
        }

        return $files;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    protected static function assertStoredFilesOfResourceExist(object $resource): array
    {
        $files = static::storedFilesOf($resource);
        self::assertNotEmpty($files, 'The resource has no stored file to check.');
        foreach ($files as [$adapter, $path]) {
            static::assertStoredFileExists($path, $adapter);
        }

        return $files;
    }

    /**
     * @param list<array{0: string, 1: string}> $files
     */
    protected static function assertStoredFilesRemoved(array $files): void
    {
        self::assertNotEmpty($files, 'No stored file was given to check.');
        foreach ($files as [$adapter, $path]) {
            static::assertStoredFileNotExists($path, $adapter);
        }
    }

    protected static function removeStoredFilesOf(object $resource): void
    {
        foreach (static::storedFilesOf($resource) as [$adapter, $path]) {
            static::filesystem($adapter)->delete($path);
        }
    }

    protected static function assertStoredFileExists(string $path, string $adapter = 'local'): void
    {
        self::assertTrue(static::filesystem($adapter)->fileExists($path), \sprintf('The file "%s" is not stored in the "%s" filesystem.', $path, $adapter));
    }

    protected static function assertStoredFileNotExists(string $path, string $adapter = 'local'): void
    {
        self::assertFalse(static::filesystem($adapter)->fileExists($path), \sprintf('The file "%s" is still stored in the "%s" filesystem.', $path, $adapter));
    }

    protected static function writeStoredFile(string $path, bool $olderThanMinimumAge = false): void
    {
        static::filesystem()->write($path, $path);
        if ($olderThanMinimumAge) {
            static::ageStoredFile($path);
        }
    }

    protected static function ageStoredFile(string $path): void
    {
        $absolutePath = static::uploadsDirectory() . '/' . $path;
        self::assertTrue(touch($absolutePath, time() - 7200), \sprintf('Could not change the modification time of %s', $absolutePath));
        clearstatcache(true, $absolutePath);
    }

    protected static function emptyUploadFilestores(): void
    {
        foreach (['local', 'public_url_local'] as $adapter) {
            $filesystem = static::filesystem($adapter);
            foreach ($filesystem->listContents('', false) as $item) {
                $item->isDir() ? $filesystem->deleteDirectory($item->path()) : $filesystem->delete($item->path());
            }
        }
    }

    protected static function addFileInfo(string $path, string $mimeType = 'image/png', int $fileSize = 1, ?int $width = 1, ?int $height = 1, ?string $filter = null): void
    {
        static::persistAndFlush(new FileInfo($path, $mimeType, $fileSize, $width, $height, $filter));
    }

    protected static function countFileInfoRows(?string $filter = null): int
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->getRepository(FileInfo::class)->count(['storedFilter' => $filter ?? '']);
    }

    /**
     * @template T of object
     *
     * @param T $resource
     *
     * @return T
     */
    private static function reloaded(object $resource): object
    {
        $reloaded = static::findByIri(static::iriOf($resource));
        self::assertNotNull($reloaded, 'The resource cannot be found any more.');

        return $reloaded;
    }
}
