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

use Silverback\ApiComponentsBundle\MessageHandler\ScanOrphanedResourcesHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, aliases: [self::LEGACY_NAME])]
class ScanOrphanedCommand extends Command
{
    public const string NAME = 'silverback:api-components:scan-orphaned';
    public const string LEGACY_NAME = 'silverback:api-components:clean-orphaned';

    public function __construct(private readonly ScanOrphanedResourcesHandler $scanner)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Scans for orphaned component groups, empty component positions and unused components, and stores the report the admin API returns. It never deletes anything.')
            ->setHelp('Prints the number of orphans of each kind; add -v to list their IRIs. Orphans are deleted through the API: DELETE per IRI, or POST /_/orphaned_resources/delete.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $report = $this->scanner->scan();
        $kinds = [
            'Component groups' => $report->componentGroups,
            'Component positions' => $report->componentPositions,
            'Components' => $report->components,
        ];
        foreach ($kinds as $heading => $iris) {
            $output->writeln(\sprintf('%s: %d', $heading, \count($iris)));
            if ($output->isVerbose()) {
                foreach ($iris as $iri) {
                    $output->writeln('  ' . $iri);
                }
            }
        }

        return Command::SUCCESS;
    }
}
