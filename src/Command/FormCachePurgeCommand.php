<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Command;

use Silverback\ApiComponentBundle\Event\CommandLogEvent;
use Silverback\ApiComponentBundle\Form\Cache\FormCachePurger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class FormCachePurgeCommand extends Command
{
    private FormCachePurger $formCachePurger;
    private EventDispatcherInterface $dispatcher;

    public function __construct(
        FormCachePurger $formCachePurger,
        EventDispatcherInterface $dispatcher,
        ?string $name = null
    ) {
        $this->formCachePurger = $formCachePurger;
        $this->dispatcher = $dispatcher;
        parent::__construct($name);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setName('silverback:api-component:form-cache-purge')
            ->setDescription('Purges the varnish cache for forms. Sets the `modified` timestamp to the file last modified date');
    }

    /**
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dispatcher->addListener(
            CommandLogEvent::class,
            static function (CommandLogEvent $event) use ($output) {
                $output->writeln($event->getSubject());
            }
        );
        $this->formCachePurger->clear();
    }
}
