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

use League\Flysystem\StorageAttributes;
use Psr\Container\ContainerExceptionInterface;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'silverback:api-components:delete-stored-files',
    description: 'Delete every stored file under a location of a filesystem',
)]
class DeleteStoredFilesCommand extends Command
{
    public function __construct(private readonly FilesystemProvider $filesystemProvider)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('filesystem', InputArgument::REQUIRED, 'The filesystem alias, as its adapter is tagged: gcloud, local, ...');
        $this->addArgument('path', InputArgument::OPTIONAL, 'Delete every file under this directory of the filesystem');
        $this->addOption('all', null, InputOption::VALUE_NONE, 'Delete every file in the filesystem; required when no path is given');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be deleted and delete nothing');
        $this->addOption('only-if-value', null, InputOption::VALUE_REQUIRED, 'Run only when this value matches --matching, such as the prefix the filesystem\'s adapter was built with');
        $this->addOption('matching', null, InputOption::VALUE_REQUIRED, 'The PCRE pattern, with delimiters, that --only-if-value must match');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $name */
        $name = $input->getArgument('filesystem');
        /** @var string|null $path */
        $path = $input->getArgument('path');
        /** @var string|null $value */
        $value = $input->getOption('only-if-value');
        /** @var string|null $pattern */
        $pattern = $input->getOption('matching');

        if (null === $path && !$input->getOption('all')) {
            $output->writeln('<error>Give a path, or --all to delete every file in the filesystem.</error>');

            return Command::INVALID;
        }

        if ((null === $value) !== (null === $pattern)) {
            $output->writeln('<error>--only-if-value and --matching go together.</error>');

            return Command::INVALID;
        }

        if (null !== $pattern && null !== $value) {
            $matched = @preg_match($pattern, $value);
            if (false === $matched) {
                $output->writeln(\sprintf('<error>--matching is not a valid pattern: %s</error>', $pattern));

                return Command::INVALID;
            }
            if (0 === $matched) {
                $output->writeln(\sprintf('<error>Refusing: "%s" does not match %s. Nothing was deleted.</error>', $value, $pattern));

                return Command::FAILURE;
            }
        }

        try {
            $filesystem = $this->filesystemProvider->getFilesystem($name);
        } catch (ContainerExceptionInterface) {
            $output->writeln(\sprintf('<error>There is no filesystem "%s".</error>', $name));

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $paths = [];
        /** @var StorageAttributes $item */
        foreach ($filesystem->listContents($path ?? '', true) as $item) {
            if ($item->isFile()) {
                $paths[] = $item->path();
            }
        }

        foreach ($paths as $file) {
            if (!$dryRun) {
                $filesystem->delete($file);
            }
            $output->writeln('  - ' . $file, $dryRun ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE);
        }
        $output->writeln(\sprintf('<info>%s %d file(s) from %s/%s.</info>', $dryRun ? 'Would delete' : 'Deleted', \count($paths), $name, $path ?? ''));

        return Command::SUCCESS;
    }
}
