<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Liip\ImagineBundle\Imagine\Filter\FilterConfiguration;
use Silverback\ApiComponentsBundle\Command\ImagineGenerateCommand;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyMultipleUploadable;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableWithImagineFilters;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Silverback\ApiComponentsBundle\Tests\Helper\Uploadable\ImagineVariantGeneratorTestTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class ImagineGenerateCommandTest extends OrphanedResourceDatabaseTestCase
{
    use ImagineVariantGeneratorTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpImagine();
    }

    protected function tearDown(): void
    {
        $this->tearDownImagine();
    }

    public function test_the_command_is_named_for_the_bundle(): void
    {
        self::assertSame('silverback:api-components:imagine:generate', ImagineGenerateCommand::NAME);
        self::assertSame(ImagineGenerateCommand::NAME, $this->command()->getName());
    }

    public function test_it_generates_what_is_missing_and_prints_the_counts(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;
        $tester = new CommandTester($this->command());

        $status = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(<<<'TXT'
            Generated: 2
            Already stored: 1
            Skipped over the memory budget: 0
            Failed: 0
            Original missing: 0

            TXT, $tester->getDisplay());
    }

    public function test_verbose_output_lists_each_variant_with_its_outcome(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;
        $tester = new CommandTester($this->command());

        $tester->execute(['--filter' => ['thumbnail', 'webp_thumbnail']], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $display = $tester->getDisplay();
        self::assertStringContainsString('  stored: thumbnail photo-1a2b3c4d.png', $display);
        self::assertStringContainsString('  generated: webp_thumbnail photo-1a2b3c4d.png', $display);
        self::assertStringNotContainsString('square_thumbnail', $display);
        self::assertStringContainsString("Generated: 1\nAlready stored: 1\n", $display);
    }

    public function test_without_verbosity_no_variant_is_listed(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $tester = new CommandTester($this->command());

        $tester->execute([]);

        self::assertStringNotContainsString('photo-1a2b3c4d.png', $tester->getDisplay());
    }

    public function test_a_failed_filter_fails_the_command(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $this->failures['thumbnail'] = new \RuntimeException('vips failed');
        $tester = new CommandTester($this->command());

        $status = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('  failed: thumbnail photo-1a2b3c4d.png', $tester->getDisplay());
        self::assertStringContainsString("Generated: 2\n", $tester->getDisplay());
        self::assertStringContainsString("Failed: 1\n", $tester->getDisplay());
    }

    public function test_skips_over_the_memory_budget_and_missing_originals_are_reported_and_the_command_still_succeeds(): void
    {
        $this->persistImage('large-1a2b3c4d.png', 8202, 5468);
        $missing = new DummyUploadableWithImagineFilters();
        $missing->setFilename('missing-1a2b3c4d.png');
        $this->persist($missing);
        $this->entityManager->flush();
        $tester = new CommandTester($this->command());

        $status = $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('  skipped: thumbnail large-1a2b3c4d.png', $tester->getDisplay());
        self::assertStringContainsString('  original missing: thumbnail missing-1a2b3c4d.png', $tester->getDisplay());
        self::assertStringContainsString("Skipped over the memory budget: 3\nFailed: 0\nOriginal missing: 3\n", $tester->getDisplay());
    }

    public function test_a_dry_run_counts_what_is_missing_and_generates_nothing(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $this->stored['thumbnail:photo-1a2b3c4d.png'] = true;
        $tester = new CommandTester($this->command());

        $status = $tester->execute(['--dry-run' => true], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame([], $this->generated);
        self::assertStringContainsString('  missing: square_thumbnail photo-1a2b3c4d.png', $tester->getDisplay());
        self::assertStringContainsString("To generate: 2\nAlready stored: 1\nFailed: 0\nOriginal missing: 0\n", $tester->getDisplay());
        self::assertStringNotContainsString('Generated:', $tester->getDisplay());
    }

    public function test_a_dry_run_that_cannot_check_a_variant_fails(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $this->storedCheckFailures['thumbnail'] = new \RuntimeException('Could not find configuration for a filter: thumbnail');
        $tester = new CommandTester($this->command());

        $status = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString("To generate: 2\nAlready stored: 0\nFailed: 1\n", $tester->getDisplay());
    }

    public function test_the_class_option_narrows_the_run(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $multiple = new DummyMultipleUploadable();
        $multiple->previewFilename = 'preview-1a2b3c4d.png';
        $this->persist($multiple);
        $this->entityManager->flush();
        $this->writeImage('preview-1a2b3c4d.png');
        $tester = new CommandTester($this->command());

        $tester->execute(['--class' => ['\\' . DummyMultipleUploadable::class]]);

        self::assertSame([['thumbnail', 'preview-1a2b3c4d.png', 'local']], $this->generated);
        self::assertStringContainsString("Generated: 1\n", $tester->getDisplay());
    }

    public function test_a_class_that_is_not_uploadable_is_refused(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $tester = new CommandTester($this->command());

        $status = $tester->execute(['--class' => [DummyMultipleUploadable::class, Page::class]]);

        self::assertSame(Command::INVALID, $status);
        self::assertSame([], $this->generated);
        self::assertStringContainsString(\sprintf('"%s" is not an uploadable class.', Page::class), $tester->getDisplay());
    }

    public function test_an_unknown_filter_is_refused_and_nothing_runs(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $tester = new CommandTester($this->command());

        $status = $tester->execute(['--filter' => ['thumbnail', 'thumbnial']]);

        self::assertSame(Command::INVALID, $status);
        self::assertSame([], $this->generated);
        self::assertSame("\"thumbnial\" is not a configured imagine filter.\n", $tester->getDisplay());
    }

    public function test_an_unknown_filter_is_refused_in_a_dry_run_too(): void
    {
        $tester = new CommandTester($this->command());

        self::assertSame(Command::INVALID, $tester->execute(['--filter' => ['missing'], '--dry-run' => true]));
        self::assertStringContainsString('"missing" is not a configured imagine filter.', $tester->getDisplay());
    }

    public function test_a_configured_filter_no_field_uses_is_accepted_and_generates_nothing(): void
    {
        $this->persistImage('photo-1a2b3c4d.png');
        $tester = new CommandTester($this->command());

        self::assertSame(Command::SUCCESS, $tester->execute(['--filter' => ['large']]));
        self::assertSame([], $this->generated);
        self::assertStringContainsString("Generated: 0\n", $tester->getDisplay());
    }

    private function command(): ImagineGenerateCommand
    {
        return new ImagineGenerateCommand($this->imagineVariantGenerator(), new FilterConfiguration([
            'thumbnail' => [],
            'square_thumbnail' => [],
            'webp_thumbnail' => [],
            'large' => [],
        ]));
    }

    private function persistImage(string $path, int $width = 10, int $height = 10): void
    {
        $object = new DummyUploadableWithImagineFilters();
        $object->setFilename($path);
        $this->persist($object);
        $this->entityManager->flush();
        $this->writeImage($path, $width, $height);
    }
}
