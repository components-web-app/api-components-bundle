<?php

namespace Silverback\ApiComponentBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadFixturesCommand extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure()
    {
        $this
            ->setName('api-component-bundle:fixtures:load')
            ->setDescription('Load fixtures by dropping database/schema first')
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('doctrine:schema:drop');
        $arguments = [
            '--force'  => true
        ];
        $cmdInput = new ArrayInput($arguments);
        $command->run($cmdInput, $output);

        $command = $this->getApplication()->find('doctrine:schema:create');
        $arguments = [];
        $cmdInput = new ArrayInput($arguments);
        $command->run($cmdInput, $output);

        $command = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [];
        $cmdInput = new ArrayInput($arguments);
        $cmdInput->setInteractive(false);
        $command->run($cmdInput, $output);

        $command = $this->getApplication()->find('app:form:cache:clear');
        $arguments = [];
        $cmdInput = new ArrayInput($arguments);
        $command->run($cmdInput, $output);
    }
}
