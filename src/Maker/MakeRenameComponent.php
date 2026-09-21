<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Maker;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakeRenameComponent extends AbstractMaker
{
    private const BLANK_NODE_PATH = '/.well-known/genid/';

    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ManagerRegistry $registry,
    ) {
    }

    public static function getCommandName(): string
    {
        return 'make:rename-component';
    }

    public static function getCommandDescription(): string
    {
        return 'Generate a Doctrine migration to rename a CWA component type (dtype + allowedComponents)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('old-name', InputArgument::OPTIONAL, 'Short class name of the component to rename (e.g. <fg=yellow>HtmlContent</>)')
            ->addArgument('new-name', InputArgument::OPTIONAL, 'Short class name for the renamed component (e.g. <fg=yellow>RichText</>)')
            ->addOption('old-fqcn', null, InputOption::VALUE_REQUIRED, 'Fully-qualified class name of the old component')
            ->addOption('new-fqcn', null, InputOption::VALUE_REQUIRED, 'Fully-qualified class name of the new component')
            ->addOption('old-dtype', null, InputOption::VALUE_REQUIRED, 'Discriminator value (dtype) stored in the database for the old component')
            ->addOption('new-dtype', null, InputOption::VALUE_REQUIRED, 'Discriminator value (dtype) stored in the database for the new component')
            ->addOption('old-iri', null, InputOption::VALUE_REQUIRED, 'Collection IRI of the old component (e.g. <fg=yellow>/component/html_contents</>), required when it cannot be resolved from the old class')
            ->addOption('new-iri', null, InputOption::VALUE_REQUIRED, 'Collection IRI of the new component (e.g. <fg=yellow>/component/rich_texts</>), required when it cannot be resolved from the new class');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $oldName = $input->getArgument('old-name') ?: $io->ask('Old component short class name (e.g. HtmlContent)');
        $newName = $input->getArgument('new-name') ?: $io->ask('New component short class name (e.g. RichText)');

        if (!$input->getOption('old-fqcn')) {
            $default = 'App\\Entity\\Component\\' . $oldName;
            $input->setOption('old-fqcn', $io->ask('Old component fully-qualified class name', $default) ?? $default);
        }

        if (!$input->getOption('old-dtype')) {
            $default = strtolower((string) $oldName);
            $input->setOption('old-dtype', $io->ask('Discriminator value (dtype) for the OLD component', $default) ?? $default);
        }

        if (!$input->getOption('new-fqcn')) {
            $default = 'App\\Entity\\Component\\' . $newName;
            $input->setOption('new-fqcn', $io->ask('New component fully-qualified class name', $default) ?? $default);
        }

        if (!$input->getOption('new-dtype')) {
            $default = strtolower((string) $newName);
            $input->setOption('new-dtype', $io->ask('Discriminator value (dtype) for the NEW component', $default) ?? $default);
        }

        $this->askForUnresolvableIri($input, $io, 'old');
        $this->askForUnresolvableIri($input, $io, 'new');
    }

    private function askForUnresolvableIri(InputInterface $input, ConsoleStyle $io, string $side): void
    {
        $option = $side . '-iri';
        if ($input->getOption($option)) {
            return;
        }

        $fqcn = (string) $input->getOption($side . '-fqcn');
        if (null !== $this->resolveIri($fqcn)) {
            return;
        }

        $io->note(\sprintf('The collection IRI for "%s" could not be resolved from API Platform, usually because the class does not exist (yet) or is not an API resource.', $fqcn));
        $input->setOption($option, $io->ask(
            \sprintf('Collection IRI of the %s component, as stored in ComponentGroup allowedComponents (e.g. /component/html_contents for HtmlContent)', strtoupper($side)),
            null,
            fn (?string $value): string => $this->validateIri($value, 'The IRI')
        ));
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        /** @var string $oldName */
        $oldName = $input->getArgument('old-name');
        /** @var string $newName */
        $newName = $input->getArgument('new-name');
        /** @var string $oldFqcn */
        $oldFqcn = $input->getOption('old-fqcn');
        /** @var string $newFqcn */
        $newFqcn = $input->getOption('new-fqcn');
        /** @var string $oldDtype */
        $oldDtype = $input->getOption('old-dtype');
        /** @var string $newDtype */
        $newDtype = $input->getOption('new-dtype');

        $oldIri = $this->iriFor($input, 'old', $oldFqcn);
        $newIri = $this->iriFor($input, 'new', $newFqcn);

        $classNameDetails = $generator->createClassNameDetails(
            'RenameComponent' . $oldName . 'To' . $newName,
            'Migrations\\'
        );

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__ . '/../Resources/skeleton/migration/RenameComponent.tpl.php',
            [
                'old_dtype' => $oldDtype,
                'new_dtype' => $newDtype,
                'old_iri' => $oldIri,
                'new_iri' => $newIri,
                'old_name' => $oldName,
                'new_name' => $newName,
                'component_table' => $this->resolveTableName(AbstractComponent::class),
                'group_table' => $this->resolveTableName(ComponentGroup::class),
            ]
        );

        $generator->writeChanges();
        $this->writeSuccessMessage($io);

        $groups = $this->registry->getRepository(ComponentGroup::class)->findAll();
        $affected = array_filter(
            $groups,
            static fn (ComponentGroup $g) => \in_array($oldIri, $g->allowedComponents ?? [], true)
        );

        if (\count($affected) > 0) {
            $io->warning(\sprintf(
                'The following ComponentGroups have allowedComponents referencing "%s". ' .
                'The migration updates the DB automatically, but you must also update ' .
                'any front-end components referencing these groups:',
                $oldIri
            ));
            $io->table(
                ['Location (IRI)', 'Reference'],
                array_map(static fn (ComponentGroup $g) => [$g->location, $g->reference], $affected)
            );
        }

        $io->text([
            '<fg=yellow>Front-end checklist after renaming:</>',
            '',
            \sprintf('  1. Rename your Vue/front-end component file from <comment>%s</comment> to <comment>%s</comment>', $oldName, $newName),
            \sprintf('  2. Update any imports or registrations referencing <comment>%s</comment>', $oldName),
            '  3. Run: <comment>php bin/console doctrine:migrations:migrate</comment>',
        ]);
    }

    /**
     * @param class-string $class
     */
    private function resolveTableName(string $class): string
    {
        $metadata = $this->registry->getManagerForClass($class)?->getClassMetadata($class);
        if (!$metadata instanceof ClassMetadata) {
            throw new \LogicException(\sprintf('Unable to resolve the database table for "%s": it is not managed by the Doctrine ORM.', $class));
        }

        return $metadata->getTableName();
    }

    private function iriFor(InputInterface $input, string $side, string $fqcn): string
    {
        $option = $side . '-iri';
        $given = $input->getOption($option);
        if (null !== $given) {
            return $this->validateIri($given, \sprintf('The --%s value', $option));
        }

        return $this->resolveIri($fqcn) ?? throw new RuntimeCommandException(\sprintf('The collection IRI for "%s" could not be resolved from API Platform, usually because the class does not exist (yet) or is not an API resource. Pass it with --%s (e.g. --%s=/component/html_contents).', $fqcn, $option, $option));
    }

    private function validateIri(?string $iri, string $subject): string
    {
        $iri = trim((string) $iri);
        if ('' === $iri || !str_starts_with($iri, '/')) {
            throw new RuntimeCommandException(\sprintf('%s must be a collection IRI path starting with "/" (e.g. /component/html_contents).', $subject));
        }
        if (str_contains($iri, self::BLANK_NODE_PATH)) {
            throw new RuntimeCommandException(\sprintf('%s "%s" is an API Platform blank node, not a component collection IRI.', $subject, $iri));
        }

        return $iri;
    }

    private function resolveIri(string $fqcn): ?string
    {
        try {
            $iri = $this->iriConverter->getIriFromResource(
                $fqcn,
                UrlGeneratorInterface::ABS_PATH,
                (new GetCollection())->withClass($fqcn)
            );
        } catch (\Throwable) {
            return null;
        }

        return null === $iri || str_contains($iri, self::BLANK_NODE_PATH) ? null : $iri;
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
