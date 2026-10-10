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
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:purge-http-cache', description: 'Flushes every cached response, API and rendered HTML together, from the HTTP cache, or with --tag or --path purges only those tags')]
class PurgeHttpCacheCommand extends Command
{
    public function __construct(
        private readonly HttpCacheFlusher $httpCacheFlusher,
        private readonly ?HttpCachePurger $httpCachePurger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tag', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Purge only this cache tag instead of flushing everything (repeatable)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Purge only the cache tag a write to the route at this path purges, e.g. /about-us (repeatable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tags = $input->getOption('tag');
        $paths = $input->getOption('path');

        if ([] === $tags && [] === $paths) {
            return $this->flush($output);
        }

        return $this->purgeTags($tags, $paths, $output);
    }

    /**
     * @param list<string> $tags
     * @param list<string> $paths
     */
    private function purgeTags(array $tags, array $paths, OutputInterface $output): int
    {
        foreach ($paths as $path) {
            if (!str_starts_with($path, '/')) {
                $output->writeln(\sprintf('<error>A path must start with "/": "%s"</error>', $path));

                return Command::INVALID;
            }
        }

        if (null === $this->httpCachePurger || !$this->httpCachePurger->canPurgeTags()) {
            $output->writeln('No HTTP cache purger is configured, so nothing was purged');

            return Command::SUCCESS;
        }

        foreach ($paths as $path) {
            $tags[] = $this->httpCachePurger->getRouteTag($path);
        }
        $tags = array_values(array_unique($tags));

        foreach ($tags as $tag) {
            if ('' === $tag) {
                $output->writeln('<error>A cache tag cannot be empty</error>');

                return Command::INVALID;
            }
            if (preg_match('/[\s,]/', $tag)) {
                $output->writeln(\sprintf('<error>A cache tag cannot contain a space or a comma: "%s"</error>', $tag));

                return Command::INVALID;
            }
        }

        try {
            $this->httpCachePurger->purgeTags($tags);
        } catch (HttpCachePurgeFailedException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('Purged the HTTP cache tags:');
        foreach ($tags as $tag) {
            $output->writeln(\sprintf('  %s', $tag));
        }

        return Command::SUCCESS;
    }

    private function flush(OutputInterface $output): int
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
