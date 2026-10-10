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

use Liip\ImagineBundle\Imagine\Cache\CacheManager;
use Liip\ImagineBundle\Service\FilterService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Imagine\ImagineFilterGenerator;
use Silverback\ApiComponentsBundle\Imagine\PhpMemoryLimit;

class ImagineFilterGeneratorTest extends TestCase
{
    private const string PATH = 'uploads/image-1a2b3c4d.jpg';

    private string $originalLimit;
    private ?string $heldAllocation = null;

    protected function setUp(): void
    {
        $this->originalLimit = (string) \ini_get('memory_limit');
        ini_set('memory_limit', '256M');
    }

    protected function tearDown(): void
    {
        $this->heldAllocation = null;
        ini_set('memory_limit', $this->originalLimit);
    }

    public function test_a_stored_filter_is_resolved_without_an_estimate_or_a_raised_limit(): void
    {
        $limitDuringCall = null;
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::once())->method('getUrlOfFilteredImage')->with(self::PATH, 'thumbnail')
            ->willReturnCallback(static function () use (&$limitDuringCall): string {
                $limitDuringCall = \ini_get('memory_limit');

                return '/media/cache/thumbnail/' . self::PATH;
            });

        $url = $this->buildGenerator($filterService, stored: true)->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468);

        self::assertSame('/media/cache/thumbnail/' . self::PATH, $url);
        self::assertSame('256M', $limitDuringCall);
    }

    public function test_an_unstored_filter_that_fits_is_generated_under_the_raised_limit_which_is_then_restored(): void
    {
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static function () use (&$limitDuringCall): string {
            $limitDuringCall = \ini_get('memory_limit');

            return '/media/cache/thumbnail/' . self::PATH;
        });

        $url = $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000);

        self::assertSame('/media/cache/thumbnail/' . self::PATH, $url);
        self::assertSame('536870912', $limitDuringCall);
        self::assertSame('256M', \ini_get('memory_limit'));
    }

    public function test_the_limit_is_restored_when_generation_throws(): void
    {
        $filterService = $this->createStub(FilterService::class);
        $failure = new \RuntimeException('generation failed');
        $filterService->method('getUrlOfFilteredImage')->willThrowException($failure);

        try {
            $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 4000, 3000);
            self::fail('The generation failure was swallowed.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }

        self::assertSame('256M', \ini_get('memory_limit'));
    }

    public function test_an_unstored_filter_that_cannot_fit_under_the_ceiling_is_skipped_and_logged(): void
    {
        $filterService = $this->createMock(FilterService::class);
        $filterService->expects(self::never())->method('getUrlOfFilteredImage');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('thumbnail'),
            self::callback(static fn (array $context): bool => 'thumbnail' === $context['filter']
                && self::PATH === $context['path']
                && 8202 === $context['width']
                && 5468 === $context['height']
                && 8202 * 5468 * 12 * 1048576 / 1000000 <= $context['estimated_bytes'] + 1
                && 536870912 === $context['memory_limit'])
        );

        $url = $this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468);

        self::assertNull($url);
        self::assertSame('256M', \ini_get('memory_limit'));
    }

    public function test_a_skipped_filter_needs_no_logger(): void
    {
        self::assertNull($this->buildGenerator($this->createStub(FilterService::class))->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468));
    }

    public function test_an_unlimited_memory_limit_never_skips_or_changes(): void
    {
        ini_set('memory_limit', '-1');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturn('/media/cache/thumbnail/' . self::PATH);

        self::assertSame('/media/cache/thumbnail/' . self::PATH, $this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 8202, 5468));
        self::assertSame('-1', \ini_get('memory_limit'));
    }

    public function test_a_limit_above_the_ceiling_is_kept_and_used_as_the_budget(): void
    {
        ini_set('memory_limit', '1G');
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static function () use (&$limitDuringCall): string {
            $limitDuringCall = \ini_get('memory_limit');

            return '/media/cache/thumbnail/' . self::PATH;
        });

        self::assertNotNull($this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', 7000, 6000));
        self::assertSame('1G', $limitDuringCall);
    }

    #[DataProvider('ceilingsThatDoNotRaise')]
    public function test_without_a_usable_ceiling_the_current_limit_is_the_budget_and_is_not_raised(?string $ceiling): void
    {
        $limitDuringCall = null;
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static function () use (&$limitDuringCall): string {
            $limitDuringCall = \ini_get('memory_limit');

            return '/media/cache/thumbnail/' . self::PATH;
        });
        $generator = $this->buildGenerator($filterService, ceiling: $ceiling);

        self::assertNotNull($generator->filteredImageUrl(self::PATH, 'thumbnail', 2000, 1500));
        self::assertSame('256M', $limitDuringCall);
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
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(static function () use (&$limitDuringCall): string {
            $limitDuringCall = \ini_get('memory_limit');

            return '/media/cache/thumbnail/' . self::PATH;
        });

        self::assertNotNull($this->buildGenerator($filterService)->filteredImageUrl(self::PATH, 'thumbnail', null, null));
        self::assertSame('536870912', $limitDuringCall);
    }

    public function test_a_limit_that_cannot_be_restored_is_logged(): void
    {
        ini_set('memory_limit', (string) (memory_get_usage(true) + 4194304));
        $previous = (string) \ini_get('memory_limit');
        $filterService = $this->createStub(FilterService::class);
        $filterService->method('getUrlOfFilteredImage')->willReturnCallback(function (): string {
            $this->heldAllocation = str_repeat('x', 16777216);

            return '/media/cache/thumbnail/' . self::PATH;
        });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            self::stringContains('could not be restored'),
            self::callback(static fn (array $context): bool => $previous === $context['previous_memory_limit'] && 536870912 === $context['memory_limit'])
        );

        $this->buildGenerator($filterService, logger: $logger)->filteredImageUrl(self::PATH, 'thumbnail', 100, 100);

        self::assertSame('536870912', \ini_get('memory_limit'));
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

    private function buildGenerator(FilterService $filterService, bool $stored = false, ?LoggerInterface $logger = null, ?string $ceiling = '512M', string $driverService = 'liip_imagine.gd'): ImagineFilterGenerator
    {
        $cacheManager = $this->createStub(CacheManager::class);
        $cacheManager->method('isStored')->willReturn($stored);

        return new ImagineFilterGenerator($filterService, $cacheManager, new PhpMemoryLimit(), $driverService, $ceiling, $logger);
    }
}
