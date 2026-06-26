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

use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\Builder\PageBuilder;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakeCwaScaffold extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:cwa-scaffold';
    }

    public static function getCommandDescription(): string
    {
        return 'Generate a starter CWA fixture scaffold (layout + home page)';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The class name for your scaffold (e.g. <fg=yellow>AppScaffold</>)')
            ->addOption('layout-ref', null, InputOption::VALUE_REQUIRED, 'Layout reference key used in <comment>$cwa->layout()</comment>', 'main')
            ->addOption('layout-component', null, InputOption::VALUE_REQUIRED, 'Layout UI component name (e.g. <comment>Primary</comment>)', 'Primary');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getOption('layout-ref') || 'main' === $input->getOption('layout-ref')) {
            $ref = $io->ask('Layout reference key (the string passed to <comment>$cwa->layout()</comment>)', 'main');
            $input->setOption('layout-ref', $ref);
        }

        if (!$input->getOption('layout-component') || 'Primary' === $input->getOption('layout-component')) {
            $component = $io->ask('Layout UI component name', 'Primary');
            $input->setOption('layout-component', $component);
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $classNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name') ?? 'AppScaffold',
            'DataFixtures\\'
        );

        $layoutRef = $input->getOption('layout-ref');
        $layoutComponent = $input->getOption('layout-component');

        $useStatements = new UseStatementGenerator([
            AbstractCwaScaffold::class,
            CwaFixtureBuilder::class,
            PageBuilder::class,
        ]);

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__ . '/../Resources/skeleton/scaffold/Scaffold.tpl.php',
            [
                'use_statements' => $useStatements,
                'layout_ref' => $layoutRef,
                'layout_component' => $layoutComponent,
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $shortName = $classNameDetails->getShortName();
        $fullName = $classNameDetails->getFullName();

        $io->text([
            'Register your scaffold as a Doctrine fixture service. In <comment>config/services.yaml</comment>:',
            '',
            '  ' . $fullName . ':',
            '    tags: [doctrine.fixture.orm]',
            '',
            'Or in a PHP config file:',
            '',
            '  $services->set(' . $shortName . '::class)->tag(\'doctrine.fixture.orm\');',
            '',
            'Then run: <comment>php bin/console doctrine:fixtures:load</comment>',
            '',
            '<fg=yellow>Tip:</> Add your own pages, page data, and components inside <comment>build()</comment>.',
            'Nav links must be added <fg=yellow>after</> <comment>$cwa->page()</comment> calls so their routes exist.',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
