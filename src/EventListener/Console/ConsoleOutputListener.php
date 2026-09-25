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

use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Service\ResetInterface;

class ConsoleOutputListener implements ResetInterface
{
    /** @var list<OutputInterface> */
    private array $outputs = [];

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        $this->outputs[] = $event->getOutput();
    }

    public function onConsoleTerminate(): void
    {
        array_pop($this->outputs);
    }

    public function getOutput(): ?OutputInterface
    {
        return $this->outputs[array_key_last($this->outputs) ?? -1] ?? null;
    }

    public function reset(): void
    {
        $this->outputs = [];
    }
}
