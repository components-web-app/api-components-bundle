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

use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetManifest;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'silverback:api-components:prune-fixture-assets',
    description: 'Delete the fixture assets that none of the given asset manifests names',
)]
class PruneFixtureAssetsCommand extends Command
{
    public function __construct(private readonly FixtureAssetStoreFactory $assetStores)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('manifests', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'Every asset manifest whose assets are kept: the current one and any that a branch or deployment still loads');
        $this->addOption('assets-store', null, InputOption::VALUE_REQUIRED, 'Prune this fixture asset store instead of the configured one: local or flysystem');
        $this->addOption('assets-dir', null, InputOption::VALUE_REQUIRED, 'Prune this local folder instead of the configured store');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List what would be deleted and delete nothing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var list<string> $manifestPaths */
        $manifestPaths = $input->getArgument('manifests');
        /** @var string|null $store */
        $store = $input->getOption('assets-store');
        /** @var string|null $directory */
        $directory = $input->getOption('assets-dir');
        $scaffoldDirectory = \dirname($manifestPaths[0]);
        try {
            $kept = [];
            foreach ($manifestPaths as $manifestPath) {
                $kept[] = FixtureAssetManifest::fromFile($manifestPath)->hashes();
            }
            $assetStore = $this->assetStores->forGeneration($scaffoldDirectory, $store, $directory) ?? $this->assetStores->forLoading($scaffoldDirectory);
        } catch (\RuntimeException|\InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $pruned = $assetStore->prune(array_merge(...$kept), $dryRun);
        $output->writeln(\sprintf('<info>%s %d asset(s) that no kept manifest names from the fixture asset store %s.</info>', $dryRun ? 'Would delete' : 'Deleted', \count($pruned), $assetStore->description));
        foreach ($pruned as $hash) {
            $output->writeln('  - ' . $hash, $dryRun ? OutputInterface::VERBOSITY_NORMAL : OutputInterface::VERBOSITY_VERBOSE);
        }

        return Command::SUCCESS;
    }
}
