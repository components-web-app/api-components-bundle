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

use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:purge-http-cache', description: 'Flushes every cached response, API and rendered HTML together, from the HTTP cache')]
class PurgeHttpCacheCommand extends Command
{
    public function __construct(private readonly HttpCacheFlusher $httpCacheFlusher)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->httpCacheFlusher->canFlush()) {
            $output->writeln('The configured HTTP cache purger cannot flush the whole cache, so nothing was flushed');

            return Command::SUCCESS;
        }

        try {
            $this->httpCacheFlusher->flush();
        } catch (HttpCacheFlushFailedException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('Flushed the HTTP cache');

        return Command::SUCCESS;
    }
}
