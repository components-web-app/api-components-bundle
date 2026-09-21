<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Features\Bootstrap;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\MinkExtension\Context\MinkContext;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Maker\MakeRenameComponent;
use Silverback\ApiComponentsBundle\Tests\Maker\RenderingMigrationGenerator;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakerContext implements Context
{
    private const BLANK_NODE_PATH = '/.well-known/genid/';

    private ?RenderingMigrationGenerator $generator = null;
    private ?\Throwable $failure = null;
    private MinkContext $minkContext;

    public function __construct(
        private readonly MakeRenameComponent $makeRenameComponent,
        private readonly IriConverterInterface $iriConverter,
        private readonly ManagerRegistry $registry,
    ) {
    }

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope): void
    {
        $this->minkContext = $scope->getEnvironment()->getContext(MinkContext::class);
    }

    /**
     * @When I generate a rename component migration with:
     */
    public function iGenerateARenameComponentMigrationWith(TableNode $table): void
    {
        $params = [];
        foreach ($table->getRowsHash() as $name => $value) {
            $params[\in_array($name, ['old-name', 'new-name'], true) ? $name : '--' . $name] = $value;
        }

        $command = new Command('make:rename-component');
        $this->makeRenameComponent->configureCommand($command, new InputConfiguration());
        $input = new ArrayInput($params, $command->getDefinition());
        $input->setInteractive(false);

        $this->generator = new RenderingMigrationGenerator();
        $this->failure = null;
        try {
            $this->makeRenameComponent->generate($input, new ConsoleStyle($input, new BufferedOutput()), $this->generator);
        } catch (\Throwable $e) {
            $this->failure = $e;
        }
    }

    /**
     * @When I run the generated rename component migration :direction
     */
    public function iRunTheGeneratedRenameComponentMigration(string $direction): void
    {
        $this->generatedMigration()->migrate($this->connection(), $direction);
    }

    /**
     * @Then API Platform resolves the class :class to a blank node IRI
     */
    public function apiPlatformResolvesTheClassToABlankNodeIri(string $class): void
    {
        $iri = $this->iriConverter->getIriFromResource($class, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($class));
        if (!str_starts_with((string) $iri, self::BLANK_NODE_PATH)) {
            throw $this->expectation(\sprintf('Expected a blank node IRI for "%s", got "%s".', $class, $iri));
        }
    }

    /**
     * @Then the rename component migration should be refused naming the option :option
     */
    public function theRenameComponentMigrationShouldBeRefusedNamingTheOption(string $option): void
    {
        if (null === $this->failure) {
            throw $this->expectation('Expected the maker to refuse to generate the migration.');
        }
        if (!str_contains($this->failure->getMessage(), $option)) {
            throw $this->expectation(\sprintf('Expected the refusal to name "%s", got: %s', $option, $this->failure->getMessage()));
        }
        if ($this->generator?->hasGenerated()) {
            throw $this->expectation('A migration was generated even though the maker refused.');
        }
    }

    /**
     * @Then the generated rename component migration should rename :oldIri to :newIri
     */
    public function theGeneratedRenameComponentMigrationShouldRename(string $oldIri, string $newIri): void
    {
        $variables = $this->generatedMigration()->getVariables();
        if ($variables['old_iri'] !== $oldIri || $variables['new_iri'] !== $newIri) {
            throw $this->expectation(\sprintf('Expected "%s" -> "%s", got "%s" -> "%s".', $oldIri, $newIri, $variables['old_iri'], $variables['new_iri']));
        }
        if (str_contains($this->generatedMigration()->getSource(), self::BLANK_NODE_PATH)) {
            throw $this->expectation('The generated migration contains a blank node IRI.');
        }
    }

    /**
     * @Then every component group should allow exactly :iri
     */
    public function everyComponentGroupShouldAllowExactly(string $iri): void
    {
        $rows = $this->connection()->fetchFirstColumn(\sprintf('SELECT allowed_components FROM %s', $this->tableName(ComponentGroup::class)));
        if ([] === $rows) {
            throw $this->expectation('There are no component groups.');
        }
        foreach ($rows as $allowed) {
            $decoded = json_decode((string) $allowed, true);
            if ([$iri] !== $decoded) {
                throw $this->expectation(\sprintf('Expected a component group to allow exactly ["%s"], got %s.', $iri, $allowed));
            }
        }
    }

    /**
     * @Then every component should have the discriminator :dtype
     */
    public function everyComponentShouldHaveTheDiscriminator(string $dtype): void
    {
        $rows = $this->connection()->fetchFirstColumn(\sprintf('SELECT dtype FROM %s', $this->tableName(AbstractComponent::class)));
        if ([] === $rows) {
            throw $this->expectation('There are no components.');
        }
        foreach ($rows as $actual) {
            if ($actual !== $dtype) {
                throw $this->expectation(\sprintf('Expected every component to have the discriminator "%s", found "%s".', $dtype, $actual));
            }
        }
    }

    private function generatedMigration(): RenderingMigrationGenerator
    {
        if (null !== $this->failure) {
            throw $this->expectation('The maker failed: ' . $this->failure->getMessage());
        }
        if (!$this->generator?->hasGenerated()) {
            throw $this->expectation('No rename component migration has been generated.');
        }

        return $this->generator;
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        return $this->registry->getConnection();
    }

    /**
     * @param class-string $class
     */
    private function tableName(string $class): string
    {
        return $this->registry->getManagerForClass($class)->getClassMetadata($class)->getTableName();
    }

    private function expectation(string $message): ExpectationException
    {
        return new ExpectationException($message, $this->minkContext->getSession()->getDriver());
    }
}
