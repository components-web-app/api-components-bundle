<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Imagine;

use League\Flysystem\Filesystem;
use League\Flysystem\Visibility;
use Liip\ImagineBundle\Binary\BinaryInterface;
use Liip\ImagineBundle\Imagine\Cache\Resolver\ResolverInterface;
use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Silverback\ApiComponentsBundle\Utility\StoredFileExtension;
use Symfony\Component\Mime\MimeTypes;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FlysystemCacheResolver implements ResolverInterface
{
    private Filesystem $filesystem;
    private string $webRoot;
    private string $cachePrefix;
    private string $cacheRoot;
    private string $visibility;

    public function __construct(Filesystem $filesystem, string $rootUrl, $cachePrefix = 'media/cache', $visibility = Visibility::PUBLIC, private readonly ?FilterConfiguration $filterConfiguration = null)
    {
        $this->filesystem = $filesystem;
        $this->webRoot = rtrim($rootUrl, '/');
        $this->cachePrefix = ltrim(str_replace('//', '/', $cachePrefix), '/');
        $this->cacheRoot = $this->cachePrefix;
        $this->visibility = $visibility;
    }

    public function getCachePrefix(): string
    {
        return $this->cachePrefix;
    }

    public function isStored($path, $filter): bool
    {
        return $this->filesystem->fileExists($this->getFilePath($path, $filter));
    }

    public function resolve($path, $filter): string
    {
        return \sprintf('%s/%s', rtrim($this->webRoot, '/'), ltrim($this->getFileUrl($path, $filter), '/'));
    }

    public function read(string $path, string $filter): ?string
    {
        $filePath = $this->getFilePath($path, $filter);
        if (!$this->filesystem->fileExists($filePath)) {
            return null;
        }

        return $this->filesystem->read($filePath);
    }

    public function store(BinaryInterface $binary, $path, $filter): void
    {
        $this->filesystem->write($this->getFilePath($path, $filter), $binary->getContent(), ['visibility' => $this->visibility, 'mimetype' => $binary->getMimeType()]);
    }

    public function remove(array $paths, array $filters): void
    {
        if (empty($paths) && empty($filters)) {
            return;
        }

        if (empty($paths)) {
            foreach ($filters as $filter) {
                $filterCacheDir = $this->cacheRoot . '/' . $filter;
                $this->filesystem->deleteDirectory($filterCacheDir);
            }

            return;
        }

        foreach ($paths as $path) {
            foreach ($filters as $filter) {
                if ($this->filesystem->fileExists($this->getFilePath($path, $filter))) {
                    $this->filesystem->delete($this->getFilePath($path, $filter));
                }
            }
        }
    }

    protected function getFilePath($path, $filter): string
    {
        return $this->getFileUrl($path, $filter);
    }

    protected function getFileUrl($path, $filter): string
    {
        $path = str_replace('://', '---', $path);
        $mimeType = $this->getOutputMimeType($filter);
        if (null !== $mimeType) {
            $path = StoredFileExtension::replaceInPath($path, $mimeType);
        }

        return $this->cachePrefix . '/' . $filter . '/' . ltrim($path, '/');
    }

    private function getOutputMimeType(string $filter): ?string
    {
        $format = $this->filterConfiguration?->all()[$filter]['format'] ?? null;
        if (!\is_string($format) || '' === $format) {
            return null;
        }

        return MimeTypes::getDefault()->getMimeTypes($format)[0] ?? null;
    }
}
