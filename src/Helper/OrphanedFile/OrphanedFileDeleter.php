<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedFile;

use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileDeletion;
use Silverback\ApiComponentsBundle\ApiResource\OrphanedFileReport;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Silverback\ApiComponentsBundle\Helper\Uploadable\UploadableFileManager;

class OrphanedFileDeleter
{
    public const string NOT_FOUND = 'not_found';
    public const string NOT_ORPHANED = 'not_orphaned';
    public const string DELETE_FAILED = 'delete_failed';

    public function __construct(
        private readonly OrphanedFileDetector $detector,
        private readonly UploadableFileManager $uploadableFileManager,
        private readonly FilesystemProvider $filesystemProvider,
        private readonly OrphanedFileReportStore $store,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param list<string>|null $paths
     */
    public function delete(?array $paths): OrphanedFileDeletion
    {
        $report = $this->detector->detect();

        $rejected = [];
        $selected = $report->orphanedFiles;
        if (null !== $paths) {
            $orphansByPath = [];
            foreach ($report->orphanedFiles as $file) {
                $orphansByPath[$file['path']][] = $file;
            }
            $selected = [];
            foreach (array_unique($paths) as $path) {
                if (isset($orphansByPath[$path])) {
                    array_push($selected, ...$orphansByPath[$path]);
                    continue;
                }
                $rejected[] = ['path' => $path, 'reason' => $this->exists($path) ? self::NOT_ORPHANED : self::NOT_FOUND];
            }
        }

        $deleted = [];
        $failed = [];
        foreach ($selected as $file) {
            try {
                $this->uploadableFileManager->deleteStoredFile(new UploadableField(adapter: $file['adapter']), $file['path']);
                $deleted[] = $file;
            } catch (FilesystemException $exception) {
                $this->logger?->error('An orphaned file could not be deleted.', [
                    'path' => $file['path'],
                    'adapter' => $file['adapter'],
                    'exception' => $exception,
                ]);
                $rejected[] = ['path' => $file['path'], 'reason' => self::DELETE_FAILED];
                $failed[] = $file;
            }
        }

        $this->store->save(new OrphanedFileReport(
            $report->generatedAt,
            array_values(array_filter($report->orphanedFiles, static fn (array $file): bool => !\in_array($file, $deleted, true))),
            $report->missingFiles,
        ));

        return new OrphanedFileDeletion($deleted, $rejected);
    }

    private function exists(string $path): bool
    {
        foreach ($this->detector->scannedAdapters() as $adapter) {
            if ($this->filesystemProvider->getFilesystem($adapter)->fileExists($path)) {
                return true;
            }
        }

        return false;
    }
}
