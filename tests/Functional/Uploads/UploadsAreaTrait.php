<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Uploads;

use ApiPlatform\Test\Client;
use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\After;
use Silverback\ApiComponentsBundle\Helper\OrphanedFile\OrphanedFileReportStore;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\UploadsTrait;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\ResponseInterface;

trait UploadsAreaTrait
{
    use ApiRequestTrait;
    use JsonTrait;
    use LogRecordsTrait;
    use UploadsTrait;

    /** @var list<string> */
    private array $uploadCopies = [];

    private ?string $originalMemoryLimit = null;

    #[After]
    protected function removeUploadCopiesAndRestoreMemoryLimit(): void
    {
        foreach ($this->uploadCopies as $directory) {
            foreach (glob($directory . '/*') ?: [] as $copy) {
                unlink($copy);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
        $this->uploadCopies = [];
        if (null !== $this->originalMemoryLimit) {
            ini_set('memory_limit', $this->originalMemoryLimit);
            $this->originalMemoryLimit = null;
        }
    }

    /**
     * @param array<string, string> $files the asset file to upload, by form field
     */
    protected function uploadMultipart(Client $client, string $url, array $files): ResponseInterface
    {
        $uploads = [];
        foreach ($files as $field => $name) {
            $directory = sys_get_temp_dir() . '/acb_upload_' . bin2hex(random_bytes(8));
            self::assertTrue(mkdir($directory));
            $this->uploadCopies[] = $directory;
            $copy = $directory . '/' . basename($name);
            copy(static::assetPath($name), $copy);
            $uploads[$field] = new UploadedFile($copy, $field, null, null, true);
        }

        return $client->request('POST', $url, [
            'headers' => ['accept' => 'application/ld+json', 'content-type' => 'multipart/form-data'],
            'extra' => ['files' => $uploads],
        ]);
    }

    protected static function assertHasStoredFile(object $resource): void
    {
        $reloaded = static::findByIri(static::iriOf($resource));
        self::assertNotNull($reloaded, 'The resource cannot be found any more.');
        self::assertTrue(method_exists($reloaded, 'getFilename'));
        self::assertNotNull($reloaded->getFilename(), 'The resource has no stored file.');
    }

    protected function setMemoryLimitJustAboveCurrentUsage(): void
    {
        $this->originalMemoryLimit ??= (string) \ini_get('memory_limit');
        ini_set('memory_limit', (string) (memory_get_usage(true) + 67108864));
    }

    /**
     * @return array{0: DummyUploadableAndPublishable, 1: DummyUploadableAndPublishable}
     */
    protected static function createUploadablePublishedWithDraft(): array
    {
        $manager = static::getContainer()->get(UploadableFileManager::class);
        $published = new DummyUploadableAndPublishable();
        $published->setPublishedAt(new \DateTime());
        $published->file = static::assetFile();
        $manager->persistFiles($published);
        $draft = new DummyUploadableAndPublishable();
        $draft->setPublishedAt(null);
        $draft->file = static::assetFile();
        $manager->persistFiles($draft);
        static::persistAndFlush($published, $draft);
        $entityManager = static::getEntityManager();
        $storedDraft = $entityManager->find(DummyUploadableAndPublishable::class, $draft->getId());
        $storedPublished = $entityManager->find(DummyUploadableAndPublishable::class, $published->getId());
        self::assertNotNull($storedDraft);
        self::assertNotNull($storedPublished);
        $storedDraft->setPublishedResource($storedPublished);
        $entityManager->flush();
        static::rebootKernel();

        return [$storedPublished, $storedDraft];
    }

    protected static function createPublishableUploadable(bool $published = true, string $file = 'image.png'): DummyUploadableAndPublishable
    {
        $resource = new DummyUploadableAndPublishable();
        $resource->setPublishedAt($published ? new \DateTime() : null);

        return static::createUploadable($resource, ['file' => $file]);
    }

    protected static function setStoredFilename(object $resource, ?string $filename): void
    {
        $reloaded = static::findByIri(static::iriOf($resource));
        self::assertNotNull($reloaded);
        $reloaded->setFilename($filename);
        static::getEntityManager()->flush();
        static::rebootKernel();
    }

    protected static function downloadLinkOf(object $resource): string
    {
        return 'http://example.com' . static::iriOf($resource) . '/download/file';
    }

    protected static function assertMissingSourceImageLogged(string $filter): void
    {
        static::assertLogged(\sprintf('a missing source image for the imagine filter "%s"', $filter), static fn (LogRecord $record): bool => Level::Warning === $record->level && $filter === ($record->context['filter'] ?? null) && ($record->context['exception'] ?? null) instanceof NotLoadableException);
    }

    protected static function assertOversizedSourceImageLogged(string $filter): void
    {
        static::assertLogged(\sprintf('an oversized source image for the imagine filter "%s"', $filter), static fn (LogRecord $record): bool => Level::Warning === $record->level && $filter === ($record->context['filter'] ?? null) && isset($record->context['estimated_bytes']));
    }

    protected static function assertStoredFileAtUrlIs(string $url, string $adapter, string $mimeType): void
    {
        $path = ltrim((string) parse_url($url, \PHP_URL_PATH), '/');
        static::assertStoredFileExists($path, $adapter);
        self::assertSame($mimeType, (new \finfo(\FILEINFO_MIME_TYPE))->buffer(static::filesystem($adapter)->read($path)));
    }

    /**
     * @param list<string> $files each as "adapter:path"
     */
    protected static function assertJsonNodeListsExactlyTheFiles(string $node, array $files): void
    {
        $expected = array_map(static function (string $file): array {
            [$adapter, $path] = explode(':', $file, 2);

            return ['adapter' => $adapter, 'path' => $path];
        }, $files);
        $actual = array_map(static fn (array $file): array => ['adapter' => $file['adapter'], 'path' => $file['path']], (array) static::jsonNode($node));
        sort($expected);
        sort($actual);
        self::assertSame($expected, $actual);
    }

    /**
     * @param list<string> $paths
     */
    protected static function requestOrphanedFilesDeletion(Client $client, array $paths): ResponseInterface
    {
        return static::requestJsonLd($client, 'POST', '/_/orphaned_files/delete', ['paths' => $paths]);
    }

    protected static function orphanedFileReportStore(): OrphanedFileReportStore
    {
        static::getEntityManager()->clear();

        return static::getContainer()->get(OrphanedFileReportStore::class);
    }
}
