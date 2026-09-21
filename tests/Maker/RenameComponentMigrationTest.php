<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Maker;

use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class RenameComponentMigrationTest extends TestCase
{
    private const COMPONENT_TABLE = '_acb_abstract_component';
    private const GROUP_TABLE = '_acb_component_group';
    private const OLD_IRI = '/component/html_contents';
    private const NEW_IRI = '/component/rich_texts';
    private const BLANK_NODE = '/.well-known/genid/956539f238167984af66';

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->executeStatement(\sprintf('CREATE TABLE %s (id VARCHAR(36) PRIMARY KEY, dtype VARCHAR(255) NOT NULL)', self::COMPONENT_TABLE));
        $this->connection->executeStatement(\sprintf('CREATE TABLE %s (id VARCHAR(36) PRIMARY KEY, allowed_components CLOB DEFAULT NULL)', self::GROUP_TABLE));
    }

    public function test_up_renames_the_discriminator_in_the_mapped_component_table(): void
    {
        $this->connection->insert(self::COMPONENT_TABLE, ['id' => 'c1', 'dtype' => 'htmlcontent']);

        $this->runMigration('up');

        $this->assertSame('richtext', $this->connection->fetchOne(\sprintf('SELECT dtype FROM %s WHERE id = ?', self::COMPONENT_TABLE), ['c1']));
    }

    public function test_up_replaces_the_old_iri_in_allowed_components_of_the_mapped_group_table(): void
    {
        $this->insertGroup('g1', [self::OLD_IRI, '/component/navigation_links']);

        $this->runMigration('up');

        $this->assertSame([self::NEW_IRI, '/component/navigation_links'], $this->allowedComponents('g1'));
    }

    public function test_down_reverts_the_discriminator_and_allowed_components(): void
    {
        $this->connection->insert(self::COMPONENT_TABLE, ['id' => 'c1', 'dtype' => 'richtext']);
        $this->insertGroup('g1', [self::NEW_IRI]);

        $this->runMigration('down');

        $this->assertSame('htmlcontent', $this->connection->fetchOne(\sprintf('SELECT dtype FROM %s WHERE id = ?', self::COMPONENT_TABLE), ['c1']));
        $this->assertSame([self::OLD_IRI], $this->allowedComponents('g1'));
    }

    public function test_up_leaves_groups_without_the_old_iri_untouched(): void
    {
        $this->insertGroup('g1', ['/component/navigation_links']);
        $before = $this->connection->fetchOne(\sprintf('SELECT allowed_components FROM %s WHERE id = ?', self::GROUP_TABLE), ['g1']);

        $this->runMigration('up');

        $this->assertSame($before, $this->connection->fetchOne(\sprintf('SELECT allowed_components FROM %s WHERE id = ?', self::GROUP_TABLE), ['g1']));
    }

    private function insertGroup(string $id, array $allowedComponents): void
    {
        $this->connection->insert(self::GROUP_TABLE, [
            'id' => $id,
            'allowed_components' => Type::getType('json')->convertToDatabaseValue($allowedComponents, $this->connection->getDatabasePlatform()),
        ]);
    }

    private function allowedComponents(string $id): array
    {
        return json_decode((string) $this->connection->fetchOne(\sprintf('SELECT allowed_components FROM %s WHERE id = ?', self::GROUP_TABLE), [$id]), true);
    }

    public function test_up_rewrites_allowed_components_to_the_given_iri_when_the_new_class_is_a_blank_node(): void
    {
        $this->insertGroup('g1', [self::OLD_IRI]);

        $fixture = $this->generate(['--new-iri' => self::NEW_IRI], newIri: self::BLANK_NODE);
        $fixture->migrate($this->connection, 'up');

        $this->assertSame([self::NEW_IRI], $this->allowedComponents('g1'));
        $this->assertStringNotContainsString('/.well-known/genid/', $fixture->generatedSource());
    }

    public function test_the_migration_is_written_by_doctrine_into_the_configured_migrations_directory(): void
    {
        $fixture = $this->generate();

        $this->assertSame(realpath($fixture->directory), realpath(\dirname($fixture->generatedPath())));
    }

    public function test_the_migration_is_a_doctrine_version_class_in_the_configured_namespace(): void
    {
        $fixture = $this->generate();

        $this->assertMatchesRegularExpression('/^Version\d{14}\.php$/', basename($fixture->generatedPath()));
        $this->assertStringContainsString('namespace ' . $fixture->namespace . ';', $fixture->generatedSource());
    }

    public function test_the_migration_is_generated_in_the_namespace_option_when_several_are_configured(): void
    {
        $other = new DoctrineMigrationsFixture();
        $fixture = new DoctrineMigrationsFixture($this->connection, [$other->namespace => $other->directory]);

        $this->generate(['--namespace' => $other->namespace], $fixture);

        $this->assertFalse($fixture->hasGenerated());
        $this->assertCount(1, $other->generatedFiles());
    }

    public function test_the_migration_defaults_to_the_first_configured_namespace(): void
    {
        $other = new DoctrineMigrationsFixture();
        $fixture = new DoctrineMigrationsFixture($this->connection, [$other->namespace => $other->directory]);

        $this->generate([], $fixture);

        $this->assertTrue($fixture->hasGenerated());
        $this->assertFalse($other->hasGenerated());
    }

    public function test_an_unconfigured_namespace_is_refused(): void
    {
        $fixture = new DoctrineMigrationsFixture($this->connection);

        try {
            $this->generate(['--namespace' => 'App\\Migrations'], $fixture);
            $this->fail('Expected an unconfigured namespace to be refused.');
        } catch (RuntimeCommandException $e) {
            $this->assertStringContainsString('App\\Migrations', $e->getMessage());
            $this->assertStringContainsString($fixture->namespace, $e->getMessage());
        }

        $this->assertFalse($fixture->hasGenerated());
    }

    public function test_the_maker_refuses_without_doctrine_migrations(): void
    {
        $this->expectException(RuntimeCommandException::class);
        $this->expectExceptionMessage('DoctrineMigrationsBundle');

        $this->generate([], null, false);
    }

    private function runMigration(string $direction): void
    {
        $this->generate()->migrate($this->connection, $direction);
    }

    private function generate(array $options = [], ?DoctrineMigrationsFixture $fixture = null, bool $withMigrations = true, string $oldIri = self::OLD_IRI, string $newIri = self::NEW_IRI): DoctrineMigrationsFixture
    {
        $fixture ??= new DoctrineMigrationsFixture($this->connection);

        $command = new Command('make:rename-component');
        $maker = new MakeRenameComponent($this->iriConverter($oldIri, $newIri), $this->registry(), $withMigrations ? $fixture->dependencyFactory : null);
        $maker->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput([
            'old-name' => 'HtmlContent',
            'new-name' => 'RichText',
            '--old-fqcn' => 'App\\Entity\\Component\\HtmlContent',
            '--new-fqcn' => 'App\\Entity\\Component\\RichText',
            '--old-dtype' => 'htmlcontent',
            '--new-dtype' => 'richtext',
        ] + $options, $command->getDefinition());
        $input->setInteractive(false);

        $maker->generate($input, new ConsoleStyle($input, new BufferedOutput()), $this->makerBundleGenerator());

        return $fixture;
    }

    private function makerBundleGenerator(): Generator
    {
        return new class extends Generator {
            public function __construct()
            {
            }

            public function createClassNameDetails(string $name, string $namespacePrefix, string $suffix = '', string $validationErrorMessage = ''): ClassNameDetails
            {
                return new ClassNameDetails('App\\' . $namespacePrefix . $name, 'App\\' . $namespacePrefix);
            }

            public function generateClass(string $className, string $templateName, array $variables = []): string
            {
                return 'src/' . str_replace('\\', '/', substr($className, 4)) . '.php';
            }

            public function writeChanges(): void
            {
            }
        };
    }

    private function iriConverter(string $oldIri, string $newIri): IriConverterInterface
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(static fn (string $class): string => str_ends_with($class, 'HtmlContent') ? $oldIri : $newIri);

        return $iriConverter;
    }

    private function registry(): ManagerRegistry
    {
        $tables = [AbstractComponent::class => self::COMPONENT_TABLE, ComponentGroup::class => self::GROUP_TABLE];

        $manager = $this->createStub(ObjectManager::class);
        $manager->method('getClassMetadata')->willReturnCallback(static function (string $class) use ($tables): ClassMetadata {
            $metadata = new ClassMetadata($class);
            $metadata->setPrimaryTable(['name' => $tables[$class]]);

            return $metadata;
        });

        $repository = $this->createStub(ObjectRepository::class);
        $repository->method('findAll')->willReturn([]);

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($manager);
        $registry->method('getRepository')->willReturn($repository);

        return $registry;
    }
}
