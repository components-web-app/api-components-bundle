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

use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedFilesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME)]
class ScanOrphanedFilesCommand extends Command
{
    public const string NAME = 'silverback:api-components:scan-orphaned-files';

    public function __construct(private readonly ScanOrphanedFilesHandler $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scans the uploadable filestores for stored files no uploadable row references, and rows whose file is missing, and stores the report the admin API returns. It never deletes anything.')
            ->setHelp('Prints the number of orphaned, unknown and missing files; add -v to list them. Orphaned files are deleted through the API: POST /_/orphaned_files/delete. Unknown files (neither named by the bundle nor ever served) and missing files are only reported.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->scanner->scan();

        $output->writeln(\sprintf('Orphaned files: %d', \count($report->orphanedFiles)));
        if ($output->isVerbose()) {
            foreach ($report->orphanedFiles as $file) {
                $output->writeln(\sprintf('  %s: %s', $file['adapter'], $file['path']));
            }
        }
        $output->writeln(\sprintf('Unknown files: %d', \count($report->unknownFiles)));
        if ($output->isVerbose()) {
            foreach ($report->unknownFiles as $file) {
                $output->writeln(\sprintf('  %s: %s', $file['adapter'], $file['path']));
            }
        }
        $output->writeln(\sprintf('Missing files: %d', \count($report->missingFiles)));
        if ($output->isVerbose()) {
            foreach ($report->missingFiles as $file) {
                $output->writeln(\sprintf('  %s: %s (%s)', $file['adapter'], $file['path'], $file['resource']));
            }
        }

        return Command::SUCCESS;
    }
}
