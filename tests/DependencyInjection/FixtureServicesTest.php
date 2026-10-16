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
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\Generator\ScaffoldGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class FixtureServicesTest extends TestCase
{
    private const string BUILDER_ID = 'silverback.api_components.fixture.cwa_fixture_builder';
    private const string GENERATOR_ID = 'silverback.api_components.fixture.scaffold_generator';
    private const string COMMAND_ID = 'silverback.api_components.command.generate_fixtures';

    public function test_the_fixture_builder_receives_the_publishable_attribute_reader_as_its_last_argument(): void
    {
        $container = $this->loadContainer();
        $arguments = $container->getDefinition(self::BUILDER_ID)->getArguments();

        self::assertSame(CwaFixtureBuilder::class, $container->getDefinition(self::BUILDER_ID)->getClass());
        self::assertCount(8, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[7]);
        self::assertSame($this->resolve($container, PublishableAttributeReader::class), $this->resolve($container, (string) $arguments[7]));
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
