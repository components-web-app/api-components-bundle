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

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Silverback\ApiComponentsBundle\Utility\MaintenanceMode;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: self::NAME, description: 'Turns the site\'s maintenance mode on or off, then purges the rendered HTML and site config from the HTTP cache; status prints on or off')]
class MaintenanceCommand extends Command
{
    public const string NAME = 'silverback:api-components:maintenance';

    private const string ON = 'on';
    private const string OFF = 'off';
    private const string STATUS = 'status';

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly ?HttpCachePurger $httpCachePurger = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('state', InputArgument::REQUIRED, 'on, off or status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $state = $input->getArgument('state');
        if (!\in_array($state, [self::ON, self::OFF, self::STATUS], true)) {
            $output->writeln(\sprintf('<error>The state must be "on", "off" or "status", not "%s"</error>', $state));

            return Command::INVALID;
        }

        try {
            $parameter = $this->switchTo($state, $output);
        } catch (DBALException $exception) {
            $output->writeln(\sprintf('<error>Maintenance mode could not be read or written in the database: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        if (null === $parameter) {
            return Command::SUCCESS;
        }

        return $this->purge($parameter, $output);
    }

    private function switchTo(string $state, OutputInterface $output): ?SiteConfigParameter
    {
        $manager = $this->registry->getManagerForClass(SiteConfigParameter::class);
        $parameter = $manager->find(SiteConfigParameter::class, MaintenanceMode::SITE_CONFIG_KEY);
        $isOn = MaintenanceMode::isOn($parameter?->getValue());

        if (self::STATUS === $state) {
            $output->writeln($isOn ? self::ON : self::OFF);

            return null;
        }

        $on = self::ON === $state;
        $parameter ??= (new SiteConfigParameter())->setKey(MaintenanceMode::SITE_CONFIG_KEY);
        if ($on === $isOn) {
            $output->writeln(\sprintf('Maintenance mode is already %s', $state));

            return $parameter;
        }

        $parameter->setValue(MaintenanceMode::storedValue($on));
        $manager->persist($parameter);
        $manager->flush();
        $output->writeln(\sprintf('Maintenance mode is now %s', $state));

        return $parameter;
    }

    private function purge(SiteConfigParameter $parameter, OutputInterface $output): int
    {
        if (null === $this->httpCachePurger || !$this->httpCachePurger->canPurgeTags()) {
            $output->writeln('No HTTP cache purger is configured, so nothing was purged');

            return Command::SUCCESS;
        }

        $tags = array_values(array_unique([HttpCachePurger::RENDERED_HTML_TAG, ...$this->httpCachePurger->getResourceTags($parameter)]));

        try {
            $this->httpCachePurger->purgeTags($tags);
        } catch (HttpCachePurgeFailedException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $output->writeln('Purged the HTTP cache tags:');
        foreach ($tags as $tag) {
            $output->writeln(\sprintf('  %s', $tag));
        }

        return Command::SUCCESS;
    }
}
