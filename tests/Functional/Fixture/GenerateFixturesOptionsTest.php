<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Fixture;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Symfony\Component\Console\Command\Command;

final class GenerateFixturesOptionsTest extends FunctionalTestCase
{
    use ConsoleTrait;

    private string $outputDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->outputDirectory = sys_get_temp_dir() . '/cwa-generate-fixtures-options-' . bin2hex(random_bytes(6));
        mkdir($this->outputDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->outputDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->outputDirectory);
        parent::tearDown();
    }

    /**
     * @return iterable<string, array{array<string, string>, int, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'an output file name that is not a class name' => [['--output' => 'generated-scaffold.php'], Command::FAILURE, 'The output file name "generated-scaffold" is not a valid PHP class name'];
        yield 'an invalid namespace' => [['--namespace' => 'App\\Data-Fixtures'], Command::FAILURE, '"App\\Data-Fixtures" is not a valid PHP namespace.'];
        yield 'an unknown asset store' => [['--assets-store' => 's3'], Command::INVALID, 'The fixture asset store "s3" is not one of "local" or "flysystem".'];
        yield 'a directory for a flysystem asset store' => [['--assets-store' => 'flysystem', '--assets-dir' => 'assets'], Command::INVALID, 'A directory can only be given for the local fixture asset store.'];
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('invalidOptions')]
    public function test_generate_fixtures_refuses_an_invalid_option_and_writes_nothing(array $options, int $status, string $message): void
    {
        $output = $this->outputDirectory . '/' . ($options['--output'] ?? 'GeneratedScaffold.php');

        $tester = self::runConsoleCommand('silverback:api-components:generate-fixtures', ['--output' => $output] + $options);

        self::assertSame($status, $tester->getStatusCode());
        self::assertStringContainsString($message, $tester->getDisplay());
        self::assertSame([], glob($this->outputDirectory . '/*'));
    }
}
