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

use Silverback\ApiComponentsBundle\Helper\Uploadable\FileInfoDeduplicator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:deduplicate-file-info', description: 'Prepares the imagine file info table for its unique index: gives originals an empty filter and removes duplicate rows')]
class DeduplicateFileInfoCommand extends Command
{
    public function __construct(private readonly FileInfoDeduplicator $fileInfoDeduplicator)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->fileInfoDeduplicator->deduplicate();

        $output->writeln(\sprintf(
            'Set an empty filter on %d original file info rows and removed %d duplicate %s.',
            $result['converted'],
            $result['removed'],
            1 === $result['removed'] ? 'row' : 'rows'
        ));

        return Command::SUCCESS;
    }
}
