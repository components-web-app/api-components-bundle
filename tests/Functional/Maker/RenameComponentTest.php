<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Maker;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\DBAL\Connection;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\RestrictedComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;
use Silverback\ApiComponentsBundle\Tests\Maker\DoctrineMigrationsFixture;
use Symfony\Bundle\MakerBundle\Command\MakerCommand;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

final class RenameComponentTest extends FunctionalTestCase
{
    use SiteTrait;

    private const string BLANK_NODE_PATH = '/.well-known/genid/';

    private ?DoctrineMigrationsFixture $migrations = null;

    public function test_a_class_that_does_not_exist_yet_resolves_to_a_blank_node_so_the_maker_refuses_without_new_iri(): void
    {
        $failure = $this->generateRenameComponentMigration([
            'old-name' => 'DummyComponent',
            'new-name' => 'RenamedComponent',
            '--old-fqcn' => DummyComponent::class,
            '--new-fqcn' => 'App\Entity\Component\RenamedComponent',
            '--old-dtype' => 'dummycomponent',
            '--new-dtype' => 'renamedcomponent',
        ]);

        self::assertStringStartsWith(self::BLANK_NODE_PATH, self::collectionIriOf('App\Entity\Component\RenamedComponent'));
        self::assertNotNull($failure, 'Expected the maker to refuse to generate the migration.');
        self::assertStringContainsString('--new-iri', $failure->getMessage());
        self::assertFalse($this->migrations?->hasGenerated(), 'A migration was generated even though the maker refused.');
    }

    public function test_both_classes_resolve_so_the_migration_uses_both_collection_iris_without_asking(): void
    {
        $failure = $this->generateRenameComponentMigration([
            'old-name' => 'DummyComponent',
            'new-name' => 'RestrictedComponent',
            '--old-fqcn' => DummyComponent::class,
            '--new-fqcn' => RestrictedComponent::class,
            '--old-dtype' => 'dummycomponent',
            '--new-dtype' => 'restrictedcomponent',
        ]);

        self::assertNull($failure, (string) $failure?->getMessage());
        $this->assertGeneratedMigrationRenames('/component/dummy_components', '/component/restricted_components');
    }

    public function test_the_generated_migration_rewrites_allowed_components_and_the_discriminator_and_reverts_them(): void
    {
        $group = self::newComponentGroup('collection');
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position = self::newComponentPosition($group, new DummyComponent(), 0);
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        self::persistAndFlush($group, $position->component, $position);
        $this->componentGroupAllows(self::iriOf($group), '/component/dummy_components');

        $failure = $this->generateRenameComponentMigration([
            'old-name' => 'DummyComponent',
            'new-name' => 'RenamedComponent',
            '--old-fqcn' => DummyComponent::class,
            '--new-fqcn' => 'App\Entity\Component\RenamedComponent',
            '--old-dtype' => 'dummycomponent',
            '--new-dtype' => 'renamedcomponent',
            '--new-iri' => '/component/renamed_components',
        ]);

        self::assertNull($failure, (string) $failure?->getMessage());
        $this->assertGeneratedMigrationRenames('/component/dummy_components', '/component/renamed_components');

        $this->generatedMigration()->migrate(self::connection(), 'up');

        self::assertEveryComponentGroupAllowsExactly('/component/renamed_components');
        self::assertEveryComponentHasTheDiscriminator('renamedcomponent');

        $this->generatedMigration()->migrate(self::connection(), 'down');

        self::assertEveryComponentGroupAllowsExactly('/component/dummy_components');
        self::assertEveryComponentHasTheDiscriminator('dummycomponent');
    }

    public function test_the_console_command_asks_for_each_missing_value_and_only_the_iri_it_cannot_resolve(): void
    {
        $other = new DoctrineMigrationsFixture();
        $this->migrations = new DoctrineMigrationsFixture(self::connection(), [$other->namespace => $other->directory]);
        $maker = new MakeRenameComponent(self::getContainer()->get(IriConverterInterface::class), self::getContainer()->get('doctrine'), $this->migrations->dependencyFactory);
        $fileManager = $this->createStub(FileManager::class);
        $fileManager->method('isNamespaceConfiguredToAutoload')->willReturn(true);
        $command = new MakerCommand($maker, $fileManager, new Generator($fileManager, 'App'));
        $command->setName(MakeRenameComponent::getCommandName());
        $tester = new CommandTester($command);
        $tester->setInputs(['DummyComponent', 'RenamedComponent', DummyComponent::class, '', '', 'renamed', '/component/renamed_components', '0']);

        $status = $tester->execute([]);

        $display = $tester->getDisplay();
        self::assertSame(Command::SUCCESS, $status, $display);
        self::assertStringContainsString('Old component fully-qualified class name', $display);
        self::assertStringContainsString('[App\Entity\Component\DummyComponent]', $display);
        self::assertStringContainsString('[dummycomponent]', $display);
        self::assertStringContainsString('[App\Entity\Component\RenamedComponent]', $display);
        self::assertStringContainsString('[renamedcomponent]', $display);
        self::assertStringContainsString('The collection IRI for "App\Entity\Component\RenamedComponent" could', $display);
        self::assertStringContainsString('Collection IRI of the NEW component', $display);
        self::assertStringNotContainsString('Collection IRI of the OLD component', $display);
        self::assertStringContainsString('Doctrine Migrations namespace to generate the migration in', $display);
        $this->assertGeneratedMigrationRenames('/component/dummy_components', '/component/renamed_components');
        $source = $this->generatedMigration()->generatedSource();
        self::assertStringContainsString("'dummycomponent'", $source);
        self::assertStringContainsString("'renamed'", $source);
    }

    /**
     * @param array<string, string> $params
     */
    private function generateRenameComponentMigration(array $params): ?\Throwable
    {
        $this->migrations = new DoctrineMigrationsFixture(self::connection());
        $maker = new MakeRenameComponent(self::getContainer()->get(IriConverterInterface::class), self::getContainer()->get('doctrine'), $this->migrations->dependencyFactory);

        $command = new Command('make:rename-component');
        $maker->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput($params, $command->getDefinition());
        $input->setInteractive(false);

        try {
            $maker->generate($input, new ConsoleStyle($input, new BufferedOutput()), (new \ReflectionClass(Generator::class))->newInstanceWithoutConstructor());
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    private function componentGroupAllows(string $iri, string $allowedComponent): void
    {
        $group = self::findByIri($iri);
        self::assertInstanceOf(ComponentGroup::class, $group);
        $group->allowedComponents = [$allowedComponent];
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();
    }

    private function generatedMigration(): DoctrineMigrationsFixture
    {
        self::assertNotNull($this->migrations);
        self::assertTrue($this->migrations->hasGenerated(), 'No rename component migration has been generated.');

        return $this->migrations;
    }

    private function assertGeneratedMigrationRenames(string $oldIri, string $newIri): void
    {
        $source = $this->generatedMigration()->generatedSource();
        self::assertStringContainsString(\sprintf('%s === $c ? %s : $c', var_export($oldIri, true), var_export($newIri, true)), $source);
        self::assertStringNotContainsString(self::BLANK_NODE_PATH, $source);
    }

    private static function assertEveryComponentGroupAllowsExactly(string $iri): void
    {
        $rows = self::connection()->fetchFirstColumn(\sprintf('SELECT allowed_components FROM %s', self::tableName(ComponentGroup::class)));
        self::assertNotSame([], $rows, 'There are no component groups.');
        foreach ($rows as $allowed) {
            self::assertSame([$iri], json_decode((string) $allowed, true));
        }
    }

    private static function assertEveryComponentHasTheDiscriminator(string $dtype): void
    {
        $rows = self::connection()->fetchFirstColumn(\sprintf('SELECT dtype FROM %s', self::tableName(AbstractComponent::class)));
        self::assertNotSame([], $rows, 'There are no components.');
        foreach ($rows as $actual) {
            self::assertSame($dtype, $actual);
        }
    }

    private static function collectionIriOf(string $class): string
    {
        return (string) self::getContainer()->get(IriConverterInterface::class)->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class));
    }

    private static function connection(): Connection
    {
        return self::getEntityManager()->getConnection();
    }

    /**
     * @param class-string $class
     */
    private static function tableName(string $class): string
    {
        return self::getEntityManager()->getClassMetadata($class)->getTableName();
    }
}
