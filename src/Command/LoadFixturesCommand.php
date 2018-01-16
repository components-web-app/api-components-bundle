<?php

namespace Silverback\ApiComponentBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadFixturesCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('app:fixtures:load')
            ->setDescription('Load fixtures by dropping database/schema first')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $command = $this->getApplication()->find('doctrine:schema:drop');
        $arguments = [
            '--force'  => true,
        ];
        $input = new ArrayInput($arguments);
        $command->run($input, $output);

        $command = $this->getApplication()->find('doctrine:schema:create');
        $arguments = [];
        $input = new ArrayInput($arguments);
        $command->run($input, $output);

        $command = $this->getApplication()->find('doctrine:fixtures:load');
        $arguments = [];
        $input = new ArrayInput($arguments);
        $input->setInteractive(false);
        $command->run($input, $output);

        $command = $this->getApplication()->find('app:form:cache:clear');
        $arguments = [];
        $input = new ArrayInput($arguments);
        $command->run($input, $output);
    }
}
