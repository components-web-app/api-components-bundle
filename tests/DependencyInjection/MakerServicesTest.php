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

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Maker\MakeApiComponent;
use Silverback\ApiComponentsBundle\Maker\MakeCwaScaffold;
use Silverback\ApiComponentsBundle\Maker\MakePageData;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Silverback\ApiComponentsBundle\Tests\Maker\DoctrineMigrationsFixture;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

class MakerServicesTest extends TestCase
{
    public static function makerProvider(): iterable
    {
        yield 'make:api-component' => ['silverback.api_components.maker.make_api_component', MakeApiComponent::class];
        yield 'make:page-data' => ['silverback.api_components.maker.make_page_data', MakePageData::class];
        yield 'make:cwa-scaffold' => ['silverback.api_components.maker.make_cwa_scaffold', MakeCwaScaffold::class];
        yield 'make:rename-component' => ['silverback.api_components.maker.make_rename_component', MakeRenameComponent::class];
    }

    #[DataProvider('makerProvider')]
    public function test_every_maker_is_a_tagged_maker_command_constructible_from_its_service_definition(string $serviceId, string $class): void
    {
        $container = new ContainerBuilder();
        $container->register(IriConverterInterface::class)->setSynthetic(true)->setPublic(true);
        $container->register(ManagerRegistry::class)->setSynthetic(true)->setPublic(true);

        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/src/Resources/config')))->load('services_maker.php');
        $container->compile();

        $container->set(IriConverterInterface::class, $this->createStub(IriConverterInterface::class));
        $container->set(ManagerRegistry::class, $this->createStub(ManagerRegistry::class));

        self::assertTrue($container->getDefinition($serviceId)->hasTag('maker.command'));
        self::assertInstanceOf($class, $container->get($serviceId));
    }

    public function test_make_rename_component_receives_the_doctrine_migrations_dependency_factory(): void
    {
        $container = $this->makerContainer();
        $container->register('doctrine.migrations.dependency_factory', DependencyFactory::class)->setSynthetic(true)->setPublic(true);
        $container->compile();

        $dependencyFactory = (new DoctrineMigrationsFixture())->dependencyFactory;
        $this->setSyntheticServices($container);
        $container->set('doctrine.migrations.dependency_factory', $dependencyFactory);

        $maker = $container->get('silverback.api_components.maker.make_rename_component');
        self::assertSame($dependencyFactory, (new \ReflectionProperty(MakeRenameComponent::class, 'migrationsDependencyFactory'))->getValue($maker));
    }

    public function test_make_rename_component_is_constructible_without_doctrine_migrations_bundle(): void
    {
        $container = $this->makerContainer();
        $container->compile();
        $this->setSyntheticServices($container);

        $maker = $container->get('silverback.api_components.maker.make_rename_component');
        self::assertNull((new \ReflectionProperty(MakeRenameComponent::class, 'migrationsDependencyFactory'))->getValue($maker));
    }

    private function makerContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(IriConverterInterface::class)->setSynthetic(true)->setPublic(true);
        $container->register(ManagerRegistry::class)->setSynthetic(true)->setPublic(true);
        (new PhpFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/src/Resources/config')))->load('services_maker.php');

        return $container;
    }

    private function setSyntheticServices(ContainerBuilder $container): void
    {
        $container->set(IriConverterInterface::class, $this->createStub(IriConverterInterface::class));
        $container->set(ManagerRegistry::class, $this->createStub(ManagerRegistry::class));
    }
}
