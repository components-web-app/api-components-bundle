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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class ImagineGenerateTest extends FunctionalTestCase
{
    use ConsoleTrait;
    use UploadsAreaTrait;

    private const string COMMAND = 'silverback:api-components:imagine:generate';

    public function test_the_command_generates_each_missing_variant_with_its_file_info_and_a_second_run_generates_nothing(): void
    {
        self::givenAnUploadableWithImagineFilters();

        $tester = self::runConsoleCommand(self::COMMAND, [], OutputInterface::VERBOSITY_VERBOSE);

        self::assertExitedWith(0, $tester);
        self::assertStringContainsString('  generated: thumbnail ', $tester->getDisplay());
        self::assertStringContainsString('Generated: 3', $tester->getDisplay());
        self::assertStringContainsString('Already stored: 0', $tester->getDisplay());
        self::assertSame(1, self::countFileInfoRows('thumbnail'));
        self::assertSame(1, self::countFileInfoRows('square_thumbnail'));
        self::assertSame(1, self::countFileInfoRows('webp_thumbnail'));

        $tester = self::runConsoleCommand(self::COMMAND);

        self::assertExitedWith(0, $tester);
        self::assertStringContainsString('Generated: 0', $tester->getDisplay());
        self::assertStringContainsString('Already stored: 3', $tester->getDisplay());
        self::assertSame(1, self::countFileInfoRows('thumbnail'));
    }

    public function test_a_run_stopped_by_its_limit_exits_3_and_the_next_run_finishes_the_variants_still_missing(): void
    {
        self::givenAnUploadableWithImagineFilters();

        $tester = self::runConsoleCommand(self::COMMAND, ['--limit' => '1']);

        self::assertExitedWith(3, $tester);
        self::assertStringContainsString('Generated: 1', $tester->getDisplay());
        self::assertStringContainsString('Stopped after 1 variant (--limit); variants are still missing, so run the command again.', $tester->getDisplay());

        $tester = self::runConsoleCommand(self::COMMAND);

        self::assertExitedWith(0, $tester);
        self::assertStringContainsString('Generated: 2', $tester->getDisplay());
        self::assertStringContainsString('Already stored: 1', $tester->getDisplay());
    }

    public function test_a_dry_run_counts_the_missing_variants_and_generates_nothing(): void
    {
        self::givenAnUploadableWithImagineFilters();

        $tester = self::runConsoleCommand(self::COMMAND, ['--dry-run' => true]);

        self::assertExitedWith(0, $tester);
        self::assertStringContainsString('To generate: 3', $tester->getDisplay());
        self::assertSame(0, self::countFileInfoRows('thumbnail'));

        $tester = self::runConsoleCommand(self::COMMAND, ['--dry-run' => true]);

        self::assertStringContainsString('To generate: 3', $tester->getDisplay());
    }

    public function test_the_filter_option_generates_only_that_filter(): void
    {
        self::givenAnUploadableWithImagineFilters();

        $tester = self::runConsoleCommand(self::COMMAND, ['--filter' => ['thumbnail']]);

        self::assertExitedWith(0, $tester);
        self::assertStringContainsString('Generated: 1', $tester->getDisplay());
        self::assertSame(1, self::countFileInfoRows('thumbnail'));
        self::assertSame(0, self::countFileInfoRows('square_thumbnail'));
    }

    public function test_an_imagine_filter_that_is_not_configured_is_refused_and_nothing_is_generated(): void
    {
        self::givenAnUploadableWithImagineFilters();

        $tester = self::runConsoleCommand(self::COMMAND, ['--filter' => ['thumbnial']]);

        self::assertExitedWith(2, $tester);
        self::assertStringContainsString('is not a configured imagine filter.', $tester->getDisplay());
        self::assertSame(0, self::countFileInfoRows('thumbnail'));
    }

    private static function givenAnUploadableWithImagineFilters(): void
    {
        static::bootKernel();
        self::emptyUploadFilestores();
        self::createUploadable(new DummyUploadableWithImagineFilters());
    }

    private static function assertExitedWith(int $code, CommandTester $tester): void
    {
        self::assertSame($code, $tester->getStatusCode(), $tester->getDisplay());
    }
}
