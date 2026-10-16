<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\EventListener\Console;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Command\ImagineGenerateCommand;
use Silverback\ApiComponentsBundle\Exception\HttpCacheFlushFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCacheFlusher;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ResetInterface;

final class FixtureLoadListener implements ResetInterface
{
    public const string COMMAND_NAME = 'doctrine:fixtures:load';

    /** @var list<bool> */
    private array $commands = [];

    public function __construct(
        private readonly ?HttpCacheFlusher $httpCacheFlusher = null,
        private readonly bool $imagineEnabled = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->commands[] = self::COMMAND_NAME === $event->getCommand()?->getName();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        if (true !== array_pop($this->commands) || $this->isLoadingFixtures()) {
            return;
        }

        $output = $event->getOutput();
        $this->flushHttpCache($output);

        if ($this->imagineEnabled && Command::SUCCESS === $event->getExitCode()) {
            $output->writeln(\sprintf('Image variants are not generated while fixtures load. Run %s to generate the image variants.', ImagineGenerateCommand::NAME));
        }
    }

    public function isLoadingFixtures(): bool
    {
        return \in_array(true, $this->commands, true);
    }

    public function reset(): void
    {
        $this->commands = [];
    }

    private function flushHttpCache(OutputInterface $output): void
    {
        if (null === $this->httpCacheFlusher || !$this->httpCacheFlusher->canFlush()) {
            $output->writeln('The configured HTTP cache purger cannot flush the whole cache, so nothing was flushed');

            return;
        }

        try {
            $this->httpCacheFlusher->flush();
        } catch (HttpCacheFlushFailedException $exception) {
            $this->logger?->error('The HTTP cache could not be flushed after loading fixtures: {message}', ['message' => $exception->getMessage(), 'exception' => $exception]);
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return;
        }

        $output->writeln('Flushed the HTTP cache');
    }
}
