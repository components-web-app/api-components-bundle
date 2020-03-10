<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Command;

use Silverback\ApiComponentBundle\ApiComponentBundleEvents;
use Silverback\ApiComponentBundle\Event\CommandLogEvent;
use Silverback\ApiComponentBundle\Form\Cache\FormCachePurger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dispatcher->addListener(
            ApiComponentBundleEvents::COMMAND_LOG,
            static function (CommandLogEvent $event) use ($output) {
                $output->writeln($event->getSubject());
            }
        );
        $this->formCachePurger->clear();
    }
}
