<?php

namespace Silverback\ApiComponentsBundle\Command;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'silverback:api-components:clean-orphaned')]
class CleanOrphanedCommand extends Command
{
    public function __construct(private readonly OrphanedResourceHelper $orphanedResourceHelper, private readonly ManagerRegistry $registry)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Checks every component and component group and will delete all orphaned components.')
            ->setDefinition([]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = 0;
        $componentGroupRepository = $this->registry->getRepository(ComponentGroup::class);
        $componentGroups = $componentGroupRepository->findAll();
        $groupsProgressBar = new ProgressBar($output, count($componentGroups));
        $groupsProgressBar->start();
        foreach ($componentGroups as $componentGroup) {
            $groupsProgressBar->advance();
            if ($this->orphanedResourceHelper->checkAndRemoveOrphanedComponentGroup($componentGroup)) {
                $count++;
            }
        }
        $groupsProgressBar->finish();
        $output->writeln('');

        $componentRepository = $this->registry->getRepository(AbstractComponent::class);
        $components = $componentRepository->findAll();
        $componentsProgressBar = new ProgressBar($output, count($componentGroups));
        $componentsProgressBar->start();
        foreach ($components as $component) {
            $componentsProgressBar->advance();
            if ($this->orphanedResourceHelper->checkAndRemoveOrphanedComponent($component)) {
                $count++;
            }
        }
        $componentsProgressBar->finish();
        $output->writeln('');

        $output->writeln(\sprintf('Removed <info>%d</info> orphaned components (excluding cascades)', $count));
        return 0;
    }
}
