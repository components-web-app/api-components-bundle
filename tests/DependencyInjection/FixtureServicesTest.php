<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Command\PruneFixtureAssetsCommand;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetLoader;
use Silverback\ApiComponentsBundle\Fixture\Asset\FixtureAssetStoreFactory;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\Generator\ScaffoldGenerator;
use Silverback\ApiComponentsBundle\Flysystem\FilesystemProvider;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FixtureServicesTest extends TestCase
{
    private const string BUILDER_ID = 'silverback.api_components.fixture.cwa_fixture_builder';
    private const string GENERATOR_ID = 'silverback.api_components.fixture.scaffold_generator';
    private const string COMMAND_ID = 'silverback.api_components.command.generate_fixtures';

    private const string ASSET_STORES_ID = 'silverback.api_components.fixture.asset_store_factory';
    private const string ASSET_LOADER_ID = 'silverback.api_components.fixture.asset_loader';
    private const string PRUNE_COMMAND_ID = 'silverback.api_components.command.prune_fixture_assets';

    public function test_the_fixture_builder_receives_the_publishable_attribute_reader_then_the_asset_loader(): void
    {
        $container = $this->loadContainer();
        $arguments = $container->getDefinition(self::BUILDER_ID)->getArguments();

        self::assertSame(CwaFixtureBuilder::class, $container->getDefinition(self::BUILDER_ID)->getClass());
        self::assertCount(9, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[7]);
        self::assertSame($this->resolve($container, PublishableAttributeReader::class), $this->resolve($container, (string) $arguments[7]));
        self::assertInstanceOf(Reference::class, $arguments[8]);
        self::assertSame(self::ASSET_LOADER_ID, $this->resolve($container, (string) $arguments[8]));
    }

    public function test_the_asset_loader_reads_through_the_asset_store_factory_and_is_reset(): void
    {
        $container = $this->loadContainer();
        $loader = $container->getDefinition(self::ASSET_LOADER_ID);

        self::assertSame(FixtureAssetLoader::class, $loader->getClass());
        self::assertSame([['method' => 'reset']], $loader->getTag('kernel.reset'));
        self::assertSame(self::ASSET_STORES_ID, $this->resolve($container, (string) $loader->getArgument(0)));
        self::assertSame(self::ASSET_LOADER_ID, $this->resolve($container, FixtureAssetLoader::class));
    }

    public function test_the_asset_store_factory_reads_the_application_filesystems(): void
    {
        $container = $this->loadContainer();
        $factory = $container->getDefinition(self::ASSET_STORES_ID);

        self::assertSame(FixtureAssetStoreFactory::class, $factory->getClass());
        self::assertFalse($factory->isAutoconfigured());
        self::assertSame($this->resolve($container, FilesystemProvider::class), $this->resolve($container, (string) $factory->getArgument('$filesystemProvider')));
        self::assertSame(self::ASSET_STORES_ID, $this->resolve($container, FixtureAssetStoreFactory::class));
    }

    public function test_both_asset_commands_use_the_asset_store_factory(): void
    {
        $container = $this->loadContainer();

        self::assertSame(self::ASSET_STORES_ID, $this->resolve($container, (string) $container->getDefinition(self::COMMAND_ID)->getArgument(1)));
        $prune = $container->getDefinition(self::PRUNE_COMMAND_ID);
        self::assertSame(PruneFixtureAssetsCommand::class, $prune->getClass());
        self::assertTrue($prune->hasTag('console.command'));
        self::assertSame(self::ASSET_STORES_ID, $this->resolve($container, (string) $prune->getArgument(0)));
        self::assertSame(self::PRUNE_COMMAND_ID, $this->resolve($container, PruneFixtureAssetsCommand::class));
    }

    public function test_the_scaffold_generator_is_a_service_the_command_uses(): void
    {
        $container = $this->loadContainer();

        self::assertSame(ScaffoldGenerator::class, $container->getDefinition(self::GENERATOR_ID)->getClass());
        self::assertFalse($container->getDefinition(self::GENERATOR_ID)->isAutoconfigured());
        self::assertSame(self::GENERATOR_ID, $this->resolve($container, ScaffoldGenerator::class));
        $references = array_map(
            fn (Reference $reference): string => $this->resolve($container, (string) $reference),
            array_filter($container->getDefinition(self::COMMAND_ID)->getArguments(), static fn ($argument): bool => $argument instanceof Reference),
        );
        self::assertContains(self::GENERATOR_ID, $references);
    }

    private function resolve(ContainerBuilder $container, string $id): string
    {
        while ($container->hasAlias($id)) {
            $id = (string) $container->getAlias($id);
        }

        return $id;
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        (new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config')))->load('services.php');

        return $container;
    }
}
