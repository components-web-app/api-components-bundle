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

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Imagine\CacheManager as SilverbackCacheManager;

final class ImagineFilterGenerator
{
    private const array MEGABYTES_PER_MEGAPIXEL = [
        'liip_imagine.gd' => 12,
        'liip_imagine.imagick' => 14,
        'liip_imagine.vips' => 8,
    ];
    private const int DEFAULT_MEGABYTES_PER_MEGAPIXEL = 14;

    private readonly int $megabytesPerMegapixel;
    private readonly ?int $ceiling;

    public function __construct(
        private readonly FilterService $filterService,
        private readonly CacheManager $cacheManager,
        private readonly PhpMemoryLimit $memoryLimit,
        string $driverService,
        ?string $memoryLimitCeiling,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->megabytesPerMegapixel = self::MEGABYTES_PER_MEGAPIXEL[$driverService] ?? self::DEFAULT_MEGABYTES_PER_MEGAPIXEL;
        $this->ceiling = null === $memoryLimitCeiling ? null : PhpMemoryLimit::parse($memoryLimitCeiling);
    }

    public function estimateBytes(int $width, int $height): int
    {
        return intdiv($width * $height * $this->megabytesPerMegapixel * 1048576, 1000000);
    }

    public function readStoredFilteredImage(string $path, string $filter): ?string
    {
        return $this->cacheManager instanceof SilverbackCacheManager ? $this->cacheManager->readStored($path, $filter) : null;
    }

    public function isStored(string $path, string $filter): bool
    {
        return $this->cacheManager->isStored($path, $filter);
    }

    public function filteredImageUrl(string $path, string $filter, ?int $width, ?int $height): ?string
    {
        return $this->filter($path, $filter, $width, $height)->url;
    }

    public function filter(string $path, string $filter, ?int $width, ?int $height): ImagineFilterResult
    {
        try {
            return $this->generate($path, $filter, $width, $height);
        } catch (NotLoadableException $exception) {
            $this->logger?->warning(\sprintf('Skipped the imagine filter "%s" because its source image "%s" could not be loaded.', $filter, $path), [
                'filter' => $filter,
                'path' => $path,
                'exception' => $exception,
            ]);
        } catch (\Exception $exception) {
            $this->logger?->error(\sprintf('Skipped the imagine filter "%s" for "%s" because generating it failed: %s', $filter, $path, $exception->getMessage()), [
                'filter' => $filter,
                'path' => $path,
                'exception' => $exception,
            ]);
        }

        return new ImagineFilterResult(ImagineFilterOutcome::Failed);
    }

    private function generate(string $path, string $filter, ?int $width, ?int $height): ImagineFilterResult
    {
        if ($this->cacheManager->isStored($path, $filter)) {
            return $this->resolved($path, $filter);
        }

        $currentLimit = $this->memoryLimit->limit();
        if (null === $currentLimit) {
            return $this->generated($path, $filter);
        }

        $budget = max($currentLimit, $this->ceiling ?? $currentLimit);
        if (null !== $width && null !== $height) {
            $estimatedBytes = $this->estimateBytes($width, $height);
            if ($this->memoryLimit->usage() + $estimatedBytes > $budget) {
                $this->logger?->warning(\sprintf('Skipped the imagine filter "%s" for "%s": generating it needs about %d MB, more than the memory limit allows.', $filter, $path, intdiv($estimatedBytes, 1048576)), [
                    'filter' => $filter,
                    'path' => $path,
                    'width' => $width,
                    'height' => $height,
                    'estimated_bytes' => $estimatedBytes,
                    'memory_limit' => $budget,
                ]);

                return new ImagineFilterResult(ImagineFilterOutcome::Skipped);
            }
        }

        $previous = $this->memoryLimit->raiseTo($budget);
        try {
            return $this->generated($path, $filter);
        } finally {
            if (null !== $previous && !$this->memoryLimit->restore($previous)) {
                $this->logger?->warning(\sprintf('The memory limit raised to generate the imagine filter "%s" could not be restored to %s.', $filter, $previous), [
                    'filter' => $filter,
                    'path' => $path,
                    'previous_memory_limit' => $previous,
                    'memory_limit' => $budget,
                ]);
            }
        }
    }

    private function generated(string $path, string $filter): ImagineFilterResult
    {
        $this->filterService->warmUpCache($path, $filter, null, true);

        return $this->resolved($path, $filter);
    }

    private function resolved(string $path, string $filter): ImagineFilterResult
    {
        return new ImagineFilterResult(ImagineFilterOutcome::Resolved, $this->cacheManager->resolve($path, $filter));
    }
}
