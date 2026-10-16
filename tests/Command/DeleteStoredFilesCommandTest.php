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

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\DeleteStoredFilesCommand;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ServiceLocator;

class DeleteStoredFilesCommandTest extends TestCase
{
    private const string REVIEW_PATTERN = '~(^|/)review/[^/]+/$~';

    private Filesystem $filesystem;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->filesystem->write('review/a/image.png', 'a');
        $this->filesystem->write('review/a/cache/thumbnail/image.webp', 'b');
        $this->filesystem->write('review/b/image.png', 'c');
        $this->filesystem->write('image.png', 'd');

        $provider = new FilesystemProvider(new ServiceLocator(['gcloud' => fn () => $this->filesystem]));
        $this->tester = new CommandTester(new DeleteStoredFilesCommand($provider));
    }

    public function test_the_command_is_registered_as_a_console_command_under_its_service_id(): void
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        self::assertTrue($container->getDefinition('silverback.api_components.command.delete_stored_files')->hasTag('console.command'));
        self::assertSame('silverback.api_components.command.delete_stored_files', (string) $container->getAlias(DeleteStoredFilesCommand::class));
    }

    public function test_it_deletes_every_file_under_the_path_and_nothing_outside_it(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute(['filesystem' => 'gcloud', 'path' => 'review/a']));

        self::assertSame(['image.png', 'review/b/image.png'], $this->storedFiles());
        self::assertStringContainsString('Deleted 2 file(s)', $this->tester->getDisplay());
    }

    public function test_it_refuses_to_empty_the_filesystem_without_a_path_or_all(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['filesystem' => 'gcloud']));

        self::assertCount(4, $this->storedFiles());
    }

    public function test_all_empties_the_filesystem(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute(['filesystem' => 'gcloud', '--all' => true]));

        self::assertSame([], $this->storedFiles());
    }

    public function test_a_dry_run_lists_the_files_and_deletes_nothing(): void
    {
        self::assertSame(Command::SUCCESS, $this->tester->execute(['filesystem' => 'gcloud', 'path' => 'review/a', '--dry-run' => true]));

        self::assertCount(4, $this->storedFiles());
        self::assertStringContainsString('Would delete 2 file(s)', $this->tester->getDisplay());
        self::assertStringContainsString('review/a/cache/thumbnail/image.webp', $this->tester->getDisplay());
    }

    public function test_deleted_files_are_listed_when_verbose(): void
    {
        $this->tester->execute(['filesystem' => 'gcloud', 'path' => 'review/a'], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        self::assertStringContainsString('review/a/image.png', $this->tester->getDisplay());
    }

    public function test_an_unknown_filesystem_is_invalid(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['filesystem' => 'missing', '--all' => true]));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function incompleteGuards(): iterable
    {
        yield 'a pattern without a value' => [['--matching' => self::REVIEW_PATTERN]];
        yield 'a value without a pattern' => [['--only-if-value' => 'review/a/']];
        yield 'an invalid pattern' => [['--only-if-value' => 'review/a/', '--matching' => '~(review~']];
    }

    /**
     * @param array<string, string> $guard
     */
    #[DataProvider('incompleteGuards')]
    public function test_an_incomplete_or_invalid_guard_is_invalid_and_deletes_nothing(array $guard): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['filesystem' => 'gcloud', '--all' => true] + $guard));

        self::assertCount(4, $this->storedFiles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unmatchedValues(): iterable
    {
        yield 'empty, as on an app deployed before it had a prefix' => [''];
        yield 'a site prefix' => ['site-a/'];
        yield 'every review app' => ['review/'];
    }

    #[DataProvider('unmatchedValues')]
    public function test_a_value_the_pattern_does_not_match_fails_and_deletes_nothing(string $value): void
    {
        $status = $this->tester->execute(['filesystem' => 'gcloud', '--all' => true, '--only-if-value' => $value, '--matching' => self::REVIEW_PATTERN]);

        self::assertSame(Command::FAILURE, $status);
        self::assertCount(4, $this->storedFiles());
    }

    public function test_a_value_the_pattern_matches_runs_the_delete(): void
    {
        $status = $this->tester->execute(['filesystem' => 'gcloud', 'path' => 'review/a', '--only-if-value' => 'site-a/review/a/', '--matching' => self::REVIEW_PATTERN]);

        self::assertSame(Command::SUCCESS, $status);
        self::assertSame(['image.png', 'review/b/image.png'], $this->storedFiles());
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        $files = $this->filesystem->listContents('', true)->filter(static fn ($item) => $item->isFile())->map(static fn ($item) => $item->path())->toArray();
        sort($files);

        return $files;
    }
}
