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

use Silverback\ApiComponentsBundle\Helper\Page\PageUiComponentPrefixer;
use Silverback\ApiComponentsBundle\Utility\PageUiComponent;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:prefix-page-ui-components', description: 'Stores every page uiComponent as the full CwaPage component name')]
class PrefixPageUiComponentsCommand extends Command
{
    public function __construct(private readonly PageUiComponentPrefixer $pageUiComponentPrefixer)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $prefixed = $this->pageUiComponentPrefixer->prefix();

        $output->writeln(\sprintf(
            'Prefixed the uiComponent of %d %s with %s.',
            $prefixed,
            1 === $prefixed ? 'page' : 'pages',
            PageUiComponent::PREFIX
        ));

        return Command::SUCCESS;
    }
}
