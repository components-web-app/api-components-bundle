<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\DBAL\Driver\PDO\Exception as PDOException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\MaintenanceCommand;
use Silverback\ApiComponentsBundle\Entity\Core\SiteConfigParameter;
use Silverback\ApiComponentsBundle\Exception\HttpCachePurgeFailedException;
use Silverback\ApiComponentsBundle\HttpCache\HttpCachePurger;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

#[AllowMockObjectsWithoutExpectations]
final class MaintenanceCommandTest extends TestCase
{
    private const string SERVICE_ID = 'silverback.api_components.command.maintenance';
    private const string PURGER_ID = 'silverback.api_components.http_cache.purger';
    private const array RESOURCE_TAGS = ['/_/site_config_parameters', '/_/site_config_parameters/maintenanceModeEnabled'];

    private EntityManagerInterface&MockObject $manager;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(EntityManagerInterface::class);
    }

    public function test_the_command_is_registered_by_its_name_without_autoconfiguration(): void
    {
        $container = $this->loadContainer();
        $definition = $container->getDefinition(self::SERVICE_ID);

        self::assertSame([['command' => 'silverback:api-components:maintenance']], $definition->getTag('console.command'));
        self::assertFalse($definition->isAutoconfigured());
        self::assertSame(self::SERVICE_ID, (string) $container->getAlias(MaintenanceCommand::class));
        self::assertSame('silverback:api-components:maintenance', MaintenanceCommand::NAME);
    }

    public function test_the_command_built_from_its_service_definition_turns_maintenance_on_and_purges(): void
    {
        $container = $this->loadContainer();
        $container->set('doctrine', $this->registry());
        $container->set(self::PURGER_ID, $this->purger(expectedTags: ['cwa-html', ...self::RESOURCE_TAGS]));
        $this->manager->expects(self::once())->method('flush');

        $tester = new CommandTester($container->get(self::SERVICE_ID));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'on']));
    }

    public function test_an_invalid_state_is_refused_before_the_database_or_the_cache_is_touched(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::never())->method('purgeTags');

        $tester = new CommandTester(new MaintenanceCommand($registry, $purger));

        self::assertSame(Command::INVALID, $tester->execute(['state' => 'ON']));
        self::assertStringContainsString('The state must be "on", "off" or "status", not "ON"', $tester->getDisplay());
    }

    public function test_on_with_no_row_persists_a_new_row_holding_true(): void
    {
        $persisted = null;
        $this->manager->method('persist')->willReturnCallback(static function (object $parameter) use (&$persisted): void {
            $persisted = $parameter;
        });
        $this->manager->expects(self::once())->method('flush');

        $tester = new CommandTester(new MaintenanceCommand($this->registry(), $this->purger(expectedTags: ['cwa-html', ...self::RESOURCE_TAGS])));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'on']));
        self::assertInstanceOf(SiteConfigParameter::class, $persisted);
        self::assertSame('maintenanceModeEnabled', $persisted->getKey());
        self::assertSame('true', $persisted->getValue());
        self::assertSame(
            "Maintenance mode is now on\nPurged the HTTP cache tags:\n  cwa-html\n  /_/site_config_parameters\n  /_/site_config_parameters/maintenanceModeEnabled\n",
            $tester->getDisplay(),
        );
    }

    public function test_off_updates_the_existing_row_to_false(): void
    {
        $parameter = (new SiteConfigParameter())->setKey('maintenanceModeEnabled')->setValue('true');
        $this->manager->expects(self::once())->method('flush');

        $tester = new CommandTester(new MaintenanceCommand($this->registry($parameter), $this->purger()));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'off']));
        self::assertSame('false', $parameter->getValue());
        self::assertStringStartsWith("Maintenance mode is now off\n", $tester->getDisplay());
    }

    public function test_an_unchanged_state_writes_nothing_and_still_purges_the_tags_of_the_row(): void
    {
        $parameter = (new SiteConfigParameter())->setKey('maintenanceModeEnabled')->setValue('1');
        $this->manager->expects(self::never())->method('persist');
        $this->manager->expects(self::never())->method('flush');
        $purger = $this->purger(expectedTags: ['cwa-html', ...self::RESOURCE_TAGS], tagsOf: $parameter);

        $tester = new CommandTester(new MaintenanceCommand($this->registry($parameter), $purger));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'on']));
        self::assertSame('1', $parameter->getValue());
        self::assertStringStartsWith("Maintenance mode is already on\nPurged the HTTP cache tags:\n", $tester->getDisplay());
    }

    public function test_the_rendered_html_tag_is_purged_once_and_first_even_when_the_resource_tags_hold_it(): void
    {
        $purger = $this->purger(expectedTags: ['cwa-html', '/_/site_config_parameters'], resourceTags: ['/_/site_config_parameters', 'cwa-html']);

        $tester = new CommandTester(new MaintenanceCommand($this->registry(), $purger));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'off']));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function statuses(): iterable
    {
        yield 'no row' => [null, 'off'];
        yield 'true' => ['true', 'on'];
        yield 'false' => ['false', 'off'];
    }

    #[DataProvider('statuses')]
    public function test_status_prints_on_or_off_only_and_neither_writes_nor_purges(?string $stored, string $expected): void
    {
        $parameter = null === $stored ? null : (new SiteConfigParameter())->setKey('maintenanceModeEnabled')->setValue($stored);
        $this->manager->expects(self::never())->method('flush');
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::never())->method('purgeTags');

        $tester = new CommandTester(new MaintenanceCommand($this->registry($parameter), $purger));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'status']));
        self::assertSame($expected . "\n", $tester->getDisplay());
    }

    public function test_a_failed_purge_exits_1_with_the_reason(): void
    {
        $purger = $this->purger();
        $purger->method('purgeTags')->willThrowException(new HttpCachePurgeFailedException('Failed to purge the HTTP cache tags "cwa-html": Could not resolve host: souin'));

        $tester = new CommandTester(new MaintenanceCommand($this->registry(), $purger));

        self::assertSame(Command::FAILURE, $tester->execute(['state' => 'on']));
        self::assertStringContainsString('Could not resolve host: souin', $tester->getDisplay());
        self::assertStringNotContainsString('Purged', $tester->getDisplay());
    }

    public function test_with_no_purger_the_state_is_still_changed_and_nothing_is_purged(): void
    {
        $this->manager->expects(self::once())->method('flush');

        $tester = new CommandTester(new MaintenanceCommand($this->registry()));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'on']));
        self::assertSame("Maintenance mode is now on\nNo HTTP cache purger is configured, so nothing was purged\n", $tester->getDisplay());
    }

    public function test_with_a_purger_that_cannot_purge_tags_nothing_is_purged(): void
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(false);
        $purger->expects(self::never())->method('purgeTags');

        $tester = new CommandTester(new MaintenanceCommand($this->registry(), $purger));

        self::assertSame(Command::SUCCESS, $tester->execute(['state' => 'on']));
        self::assertStringContainsString('nothing was purged', $tester->getDisplay());
    }

    public function test_a_database_failure_exits_1_with_the_reason_and_purges_nothing(): void
    {
        $this->manager->method('find')->willThrowException(new ConnectionException(new PDOException('could not connect to db-host'), null));
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->expects(self::never())->method('purgeTags');

        $tester = new CommandTester(new MaintenanceCommand($this->registry(find: false), $purger));

        self::assertSame(Command::FAILURE, $tester->execute(['state' => 'on']));
        self::assertStringContainsString('Maintenance mode could not be read or written in the database: ', $tester->getDisplay());
        self::assertStringContainsString('could not connect to db-host', $tester->getDisplay());
    }

    private function registry(?SiteConfigParameter $parameter = null, bool $find = true): ManagerRegistry
    {
        if ($find) {
            $this->manager->method('find')->willReturnCallback(static fn (string $class, mixed $id): ?SiteConfigParameter => SiteConfigParameter::class === $class && 'maintenanceModeEnabled' === $id ? $parameter : null);
        }
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ?EntityManagerInterface => SiteConfigParameter::class === $class ? $this->manager : null);

        return $registry;
    }

    /**
     * @param list<string>|null $expectedTags
     * @param list<string>      $resourceTags
     */
    private function purger(?array $expectedTags = null, array $resourceTags = self::RESOURCE_TAGS, ?SiteConfigParameter $tagsOf = null): HttpCachePurger&MockObject
    {
        $purger = $this->createMock(HttpCachePurger::class);
        $purger->method('canPurgeTags')->willReturn(true);
        $purger->method('getResourceTags')->willReturnCallback(static fn (object $resource): array => null === $tagsOf || $resource === $tagsOf ? $resourceTags : []);
        if (null !== $expectedTags) {
            $purger->expects(self::once())->method('purgeTags')->with(self::identicalTo($expectedTags));
        }

        return $purger;
    }

    private function loadContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../src/Resources/config'));
        $loader->load('services_doctrine_orm_http_cache_purger.php');

        return $container;
    }
}
