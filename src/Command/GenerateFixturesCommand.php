<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Command;

use Silverback\ApiComponentsBundle\Fixture\Generator\ScaffoldGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'silverback:api-components:generate-fixtures',
    description: 'Walk the database and output an AbstractCwaScaffold-compatible PHP fixture class',
)]
class GenerateFixturesCommand extends Command
{
    private const string IDENTIFIER = '[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*';

    public function __construct(private readonly ScaffoldGenerator $generator)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path; the class is named after the file', 'src/DataFixtures/GeneratedScaffold.php');
        $this->addOption('namespace', null, InputOption::VALUE_REQUIRED, 'Namespace of the generated class', 'App\\DataFixtures');
        $this->addOption('keep-timestamps', null, InputOption::VALUE_NONE, 'Write every publish date and go-live date exactly, instead of publishing past dates at load time');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $outputPath */
        $outputPath = $input->getOption('output');
        $className = pathinfo($outputPath, \PATHINFO_FILENAME);
        if (1 !== preg_match('/^' . self::IDENTIFIER . '$/', $className)) {
            $output->writeln(\sprintf('<error>The output file name "%s" is not a valid PHP class name, so the class could not be named after it.</error>', $className));

            return Command::FAILURE;
        }
        $namespace = trim((string) $input->getOption('namespace'), '\\');
        if (1 !== preg_match('/^' . self::IDENTIFIER . '(\\\\' . self::IDENTIFIER . ')*$/', $namespace)) {
            $output->writeln(\sprintf('<error>"%s" is not a valid PHP namespace.</error>', $namespace));

            return Command::FAILURE;
        }

        $assetsDirectory = \dirname($outputPath) . '/assets';
        $scaffold = $this->generator->generate($namespace, $className, $assetsDirectory, (bool) $input->getOption('keep-timestamps'));
        file_put_contents($outputPath, $scaffold->code);

        $output->writeln(\sprintf('<info>Fixture class written to %s</info>', $outputPath));
        if ($scaffold->assetCount > 0) {
            $output->writeln(\sprintf('<info>%d stored file(s) exported to %s</info>', $scaffold->assetCount, $assetsDirectory));
        }
        if ([] !== $scaffold->notReproduced) {
            $output->writeln(\sprintf('<comment>%d item(s) could not be reproduced:</comment>', \count($scaffold->notReproduced)));
            foreach ($scaffold->notReproduced as $message) {
                $output->writeln('  - ' . $message);
            }
        }

        return Command::SUCCESS;
    }
}
