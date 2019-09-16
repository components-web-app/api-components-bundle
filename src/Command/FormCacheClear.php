<?php

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Command;

use Silverback\ApiComponentBundle\Cache\FormCacheClearer;
use Silverback\ApiComponentBundle\Event\CommandNotifyEvent;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FormCacheClear extends Command
{
    private $cacheClearer;
    private $dispatcher;

    public function __construct(
        FormCacheClearer $cacheClearer,
        EventDispatcherInterface $dispatcher,
        ?string $name = null
    ) {
        $this->cacheClearer = $cacheClearer;
        $this->dispatcher = $dispatcher;
        parent::__construct($name);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function configure(): void
    {
        $this
            ->setName('silverback:api-component:clear-form-cache')
            ->setDescription('Purges the varnish cache for forms where files have been updated');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->dispatcher->addListener(
            FormCacheClearer::FORM_CACHE_EVENT_NAME,
            static function (CommandNotifyEvent $event) use ($output) {
                $output->writeln($event->getSubject());
            }
        );
        $this->cacheClearer->clear();
    }
}
