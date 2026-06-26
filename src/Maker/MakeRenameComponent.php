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
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakeRenameComponent extends AbstractMaker
{
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
            ->addOption('new-dtype', null, InputOption::VALUE_REQUIRED, 'Discriminator value (dtype) stored in the database for the new component');
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

        $oldIri = $this->resolveIri($oldFqcn, $oldName);
        $newIri = $this->resolveIri($newFqcn, $newName);

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

    private function resolveIri(string $fqcn, string $shortName): string
    {
        try {
            return $this->iriConverter->getIriFromResource(
                $fqcn,
                UrlGeneratorInterface::ABS_PATH,
                (new GetCollection())->withClass($fqcn)
            );
        } catch (\Throwable) {
            return '/component/' . strtolower(preg_replace('/([A-Z])/', '-$1', lcfirst($shortName)));
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
