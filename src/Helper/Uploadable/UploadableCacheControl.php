<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\Uploadable;

use League\Flysystem\Filesystem;
use Silverback\ApiComponentsBundle\Annotation\UploadableField;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemFactory;
use Silverback\ApiComponentsBundle\Utility\StoredFileWriteConfig;
use Symfony\Contracts\Service\ResetInterface;

final class UploadableCacheControl implements ResetInterface
{
    /**
     * @var array<string, ?string>
     */
    private array $variantSources = [];

    public function __construct(
        private readonly FilesystemFactory $filesystemFactory,
        private readonly ?string $original,
        private readonly ?string $imagine,
    ) {
    }

    public function forOriginal(UploadableField $field): ?string
    {
        return self::resolve($field->cacheControl, $this->original);
    }

    public function forImagine(UploadableField $field): ?string
    {
        return self::resolve($field->imagineCacheControl, $this->imagine);
    }

    public function registerVariantSource(string $path, UploadableField $field): void
    {
        $this->variantSources[$path] = $this->forImagine($field);
    }

    public function forVariantOf(string $path): ?string
    {
        return \array_key_exists($path, $this->variantSources) ? $this->variantSources[$path] : $this->imagine;
    }

    public function writeConfig(Filesystem $filesystem, array $config, ?string $cacheControl): array
    {
        return StoredFileWriteConfig::withCacheControl($this->filesystemFactory->getAdapterOf($filesystem), $config, $cacheControl);
    }

    public function reset(): void
    {
        $this->variantSources = [];
    }

    private static function resolve(string|false|null $override, ?string $default): ?string
    {
        if (false === $override) {
            return null;
        }

        return $override ?? $default;
    }
}
