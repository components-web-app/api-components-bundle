<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Command;

use Silverback\ApiComponentsBundle\Event\CommandLogEvent;
use Silverback\ApiComponentsBundle\Helper\Form\FormCachePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[AsCommand(name: 'silverback:api-components:form-cache-purge', description: 'Purges the varnish cache for forms. Sets the `modified` timestamp to the file last modified date')]
class FormCachePurgeCommand extends Command
{
    private FormCachePurger $formCachePurger;
    private EventDispatcherInterface $dispatcher;

    public function __construct(FormCachePurger $formCachePurger, EventDispatcherInterface $dispatcher)
    {
        parent::__construct();
        $this->formCachePurger = $formCachePurger;
        $this->dispatcher = $dispatcher;
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->dispatcher->addListener(
            CommandLogEvent::class,
            static function (CommandLogEvent $event) use ($output) {
                $output->writeln($event->getSubject());
            }
        );
        $this->formCachePurger->clear();
        $output->writeln('Form cache purge complete');

        return 0;
    }
}
