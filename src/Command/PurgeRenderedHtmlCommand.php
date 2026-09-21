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

use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:purge-rendered-html', description: 'Purges every page of rendered front-end HTML from the HTTP cache by its `cwa-html` tag')]
class PurgeRenderedHtmlCommand extends Command
{
    public function __construct(private readonly ?HttpCachePurger $httpCachePurger)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (null === $this->httpCachePurger) {
            $output->writeln('No HTTP cache purger is configured, so nothing was purged');

            return Command::SUCCESS;
        }

        $this->httpCachePurger->purgeRenderedHtml();
        $output->writeln(\sprintf('Purged the `%s` cache tag', HttpCachePurger::RENDERED_HTML_TAG));

        return Command::SUCCESS;
    }
}
