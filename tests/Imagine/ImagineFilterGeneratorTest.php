<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Imagine;

use Liip\ImagineBundle\Exception\Binary\Loader\NotLoadableException;
use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterOutcome;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;

class ImagineFilterGeneratorTest extends TestCase
{
    use MemoryLimitAboveUsageTrait;

    private const string PATH = 'uploads/image-1a2b3c4d.jpg';

    private string $originalLimit;
    private string $limit;
    private string $ceiling;
    private ?string $heldAllocation = null;

    protected function setUp(): void
    {
        $this->originalLimit = (string) \ini_get('memory_limit');
        $this->limit = self::megabytesAboveUsage(256);
        $this->ceiling = self::megabytesAboveUsage(512);
        self::setMemoryLimit($this->limit);
    }

    protected function tearDown(): void
    {
        $this->heldAllocation = null;
        ini_set('memory_limit', $this->originalLimit);
    }

    public function test_a_stored_filter_is_resolved_with_one_storage_check_without_generating_or_raising_the_limit(): void
    {
        $limitDuringCall = null;
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('warmUpCache');
        $filterService->expects(self::never())->method('getUrlOfFilteredImage');
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())->method('isStored')->with(self::PATH, 'thumbnail')->willReturn(true);
        $cacheManager->expects(self::once())->method('resolve')->with(self::PATH, 'thumbnail')
            ->willReturnCallback(static function () use (&$limitDuringCall): string {
                $limitDuringCall = \ini_get('memory_limit');

                return '/media/cache/thumbnail/' . self::PATH;
            });

        $result = $this->buildGeneratorWithCacheManager($filterService, $cacheManager)->filter(self::PATH, 'thumbnail', 8202, 5468);

        self::assertSame(ImagineFilterOutcome::Resolved, $result->outcome);
        self::assertSame('/media/cache/thumbnail/' . self::PATH, $result->url);
        self::assertSame($this->limit, $limitDuringCall);
    }

    #[DataProvider('memoryLimits')]
    public function test_an_unstored_filter_is_checked_once_then_generated_and_resolved(?string $memoryLimit): void
    {
        if (null !== $memoryLimit) {
            ini_set('memory_limit', $memoryLimit);
        }
        $calls = [];
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('getUrlOfFilteredImage');
        $filterService->expects(self::once())->method('warmUpCache')->with(self::PATH, 'thumbnail', null, true)
            ->willReturnCallback(static function () use (&$calls): bool {
                $calls[] = 'warmUpCache';

                return true;
            });
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())->method('isStored')->with(self::PATH, 'thumbnail')
            ->willReturnCallback(static function () use (&$calls): bool {
                $calls[] = 'isStored';

                return false;
            });
        $cacheManager->expects(self::once())->method('resolve')->with(self::PATH, 'thumbnail')
            ->willReturnCallback(static function () use (&$calls): string {
                $calls[] = 'resolve';

                return '/media/cache/thumbnail/' . self::PATH;
            });

        $result = $this->buildGeneratorWithCacheManager($filterService, $cacheManager)->filter(self::PATH, 'thumbnail', 400, 300);

        self::assertSame(ImagineFilterOutcome::Resolved, $result->outcome);
        self::assertSame('/media/cache/thumbnail/' . self::PATH, $result->url);
        self::assertSame(['isStored', 'warmUpCache', 'resolve'], $calls);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function memoryLimits(): iterable
    {
        yield 'a memory limit' => [null];
        yield 'an unlimited memory limit' => ['-1'];
    }

    public function test_an_unstored_filter_that_fits_is_generated_under_the_raised_limit_which_is_then_restored(): void
    {
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(static function () use (&$limitDuringCall): bool {
            $limitDuringCall = \ini_get('memory_limit');

            return true;
        });

        $url = $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame('/media/cache/thumbnail/' . self::PATH, $url);
        self::assertSame((string) self::bytesOf($this->ceiling), $limitDuringCall);
        self::assertSame($this->limit, \ini_get('memory_limit'));
    }

    public function test_a_filter_whose_generation_fails_is_skipped_and_logged_and_the_limit_restored(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $failure = new \RuntimeException('heifsave: Unsupported compression');
        $filterService->method('warmUpCache')->willThrowException($failure);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $logger->expects(self::once())->method('error')->with(
            self::stringContains('thumbnail'),
            self::callback(static fn (array $context): bool => 'thumbnail' === $context['filter'] && self::PATH === $context['path'] && $failure === $context['exception'])
        );

        self::assertNull($this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000));
        self::assertSame($this->limit, \ini_get('memory_limit'));
    }

    public function test_a_stored_filter_that_fails_to_resolve_is_skipped(): void
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturn(true);
        $cacheManager->method('resolve')->willThrowException(new \RuntimeException('resolve failed'));

        $result = $this->buildGeneratorWithCacheManager($this->createStub(FilterService::class), $cacheManager)->filter(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame(ImagineFilterOutcome::Failed, $result->outcome);
        self::assertNull($result->url);
    }

    public function test_a_filter_whose_source_image_cannot_be_loaded_is_skipped_with_a_warning(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $missing = new NotLoadableException('Source image not found.');
        $filterService->method('warmUpCache')->willThrowException($missing);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('could not be loaded'),
            self::callback(static fn (array $context): bool => 'thumbnail' === $context['filter'] && self::PATH === $context['path'] && $missing === $context['exception'])
        );

        self::assertNull($this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000));
    }

    public function test_a_failed_filter_needs_no_logger(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willThrowException(new \RuntimeException('generation failed'));

        self::assertNull($this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000));
    }

    public function test_a_php_error_during_generation_is_not_swallowed_and_the_limit_is_restored(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $error = new \Error('bug');
        $filterService->method('warmUpCache')->willThrowException($error);

        try {
            $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000);
            self::fail('The error was swallowed.');
        } catch (\Error $exception) {
            self::assertSame($error, $exception);
        }

        self::assertSame($this->limit, \ini_get('memory_limit'));
    }

    public function test_an_unstored_filter_that_cannot_fit_under_the_ceiling_is_skipped_and_logged(): void
    {
        $ceilingBytes = self::bytesOf($this->ceiling);
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('warmUpCache');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('thumbnail'),
            self::callback(static fn (array $context): bool => 'thumbnail' === $context['filter']
                && self::PATH === $context['path']
                && 8202 === $context['width']
                && 5468 === $context['height']
                && 8202 * 5468 * 12 * 1048576 / 1000000 <= $context['estimated_bytes'] + 1
                && $ceilingBytes === $context['memory_limit'])
        );

        $url = $this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468);

        self::assertNull($url);
        self::assertSame($this->limit, \ini_get('memory_limit'));
    }

    public function test_a_skipped_filter_needs_no_logger(): void
    {
        self::assertNull($this->buildGenerator($this->createStub(FilterService::class))->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468));
    }

    public function test_an_unlimited_memory_limit_never_skips_or_changes(): void
    {
        ini_set('memory_limit', '-1');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturn(true);

        self::assertSame('/media/cache/thumbnail/' . self::PATH, $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468));
        self::assertSame('-1', \ini_get('memory_limit'));
    }

    public function test_a_limit_above_the_ceiling_is_kept_and_used_as_the_budget(): void
    {
        $aboveCeiling = self::megabytesAboveUsage(1024);
        self::setMemoryLimit($aboveCeiling);
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(static function () use (&$limitDuringCall): bool {
            $limitDuringCall = \ini_get('memory_limit');

            return true;
        });

        self::assertNotNull($this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 7000, 6000));
        self::assertSame($aboveCeiling, $limitDuringCall);
    }

    #[DataProvider('ceilingsThatDoNotRaise')]
    public function test_without_a_usable_ceiling_the_current_limit_is_the_budget_and_is_not_raised(?string $ceiling): void
    {
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(static function () use (&$limitDuringCall): bool {
            $limitDuringCall = \ini_get('memory_limit');

            return true;
        });
        $generator = $this->buildGeneratorWithCeiling($filterService, $ceiling);

        self::assertNotNull($generator->filteredImageUrl(self::PATH, 'thumbnail', 2000, 1500));
        self::assertSame($this->limit, $limitDuringCall);
        self::assertNull($generator->filteredImageUrl(self::PATH, 'thumbnail', 4000, 6000));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function ceilingsThatDoNotRaise(): iterable
    {
        yield 'not configured' => [null];
        yield 'not a size' => ['lots'];
    }

    public function test_an_image_with_unknown_dimensions_is_generated_under_the_raised_limit(): void
    {
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(static function () use (&$limitDuringCall): bool {
            $limitDuringCall = \ini_get('memory_limit');

            return true;
        });

        self::assertNotNull($this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', null, null));
        self::assertSame((string) self::bytesOf($this->ceiling), $limitDuringCall);
    }

    public function test_a_limit_that_cannot_be_restored_is_logged(): void
    {
        $ceilingBytes = self::bytesOf($this->ceiling);
        ini_set('memory_limit', (string) (memory_get_usage(true) + 4194304));
        $previous = (string) \ini_get('memory_limit');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturnCallback(function (): bool {
            $this->heldAllocation = str_repeat('x', 16777216);

            return true;
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('could not be restored'),
            self::callback(static fn (array $context): bool => $previous === $context['previous_memory_limit'] && $ceilingBytes === $context['memory_limit'])
        );

        $this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 100, 100);

        self::assertSame((string) self::bytesOf($this->ceiling), \ini_get('memory_limit'));
    }

    public function test_a_filter_that_is_generated_or_resolved_is_reported_as_resolved_with_its_url(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willReturn(true);

        $result = $this->buildGenerator($filterService)->filter(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame(ImagineFilterOutcome::Resolved, $result->outcome);
        self::assertSame('/media/cache/thumbnail/' . self::PATH, $result->url);
    }

    public function test_a_filter_over_the_memory_budget_is_reported_as_skipped(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('warmUpCache');

        $result = $this->buildGenerator($filterService)->filter(self::PATH, 'thumbnail', 8202, 5468);

        self::assertSame(ImagineFilterOutcome::Skipped, $result->outcome);
        self::assertNull($result->url);
    }

    public function test_a_filter_whose_generation_fails_is_reported_as_failed(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willThrowException(new \RuntimeException('generation failed'));

        $result = $this->buildGenerator($filterService)->filter(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame(ImagineFilterOutcome::Failed, $result->outcome);
        self::assertNull($result->url);
    }

    public function test_a_filter_whose_source_image_cannot_be_loaded_is_reported_as_failed(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('warmUpCache')->willThrowException(new NotLoadableException('Source image not found.'));

        $result = $this->buildGenerator($filterService)->filter(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame(ImagineFilterOutcome::Failed, $result->outcome);
        self::assertNull($result->url);
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function storedStates(): iterable
    {
        yield 'stored' => [true];
        yield 'not stored' => [false];
    }

    #[DataProvider('storedStates')]
    public function test_whether_a_filter_is_stored_is_the_cache_manager_answer_for_that_path_and_filter(bool $stored): void
    {
        $cacheManager = $this->createMock(CacheManager::class);
        $cacheManager->expects(self::once())->method('isStored')->with(self::PATH, 'thumbnail')->willReturn($stored);
        $generator = new ImagineFilterGenerator($this->createStub(FilterService::class), $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', '512M');

        self::assertSame($stored, $generator->isStored(self::PATH, 'thumbnail'));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function driverRates(): iterable
    {
        yield 'gd' => ['liip_imagine.gd', 12];
        yield 'imagick' => ['liip_imagine.imagick', 14];
        yield 'vips' => ['liip_imagine.vips', 8];
        yield 'gmagick' => ['liip_imagine.gmagick', 14];
        yield 'unknown' => ['app.custom_imagine', 14];
    }

    #[DataProvider('driverRates')]
    public function test_the_estimate_uses_the_measured_rate_for_the_driver(string $driverService, int $megabytesPerMegapixel): void
    {
        $generator = $this->buildGenerator($this->createStub(FilterService::class), driverService: $driverService);

        self::assertSame($megabytesPerMegapixel * 1048576, $generator->estimateBytes(1000, 1000));
        self::assertSame($megabytesPerMegapixel * 1048576 * 45, $generator->estimateBytes(9000, 5000));
    }

    private function buildGenerator(FilterService $filterService, bool $stored = false, ?LoggerInterface $logger = null, string $driverService = 'liip_imagine.gd'): ImagineFilterGenerator
    {
        return $this->buildGeneratorWithCeiling($filterService, $this->ceiling, $stored, $logger, $driverService);
    }

    private function buildGeneratorWithCeiling(FilterService $filterService, ?string $ceiling, bool $stored = false, ?LoggerInterface $logger = null, string $driverService = 'liip_imagine.gd'): ImagineFilterGenerator
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturn($stored);
        $cacheManager->method('resolve')->willReturnCallback(static fn (string $path, string $filter): string => '/media/cache/' . $filter . '/' . $path);

        return new ImagineFilterGenerator($filterService, $cacheManager, new PhpMemoryLimit(), $driverService, $ceiling, $logger);
    }

    private function buildGeneratorWithCacheManager(FilterService $filterService, CacheManager $cacheManager): ImagineFilterGenerator
    {
        return new ImagineFilterGenerator($filterService, $cacheManager, new PhpMemoryLimit(), 'liip_imagine.gd', $this->ceiling);
    }
}
