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

use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Fixture\Generator\Code\VariableNamer;
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
    public function __construct(private readonly PublishableAttributeReader $publishableAttributeReader)
    {
    }

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
            ->addOption('layout-component', null, InputOption::VALUE_REQUIRED, 'Layout UI component name (e.g. <comment>Primary</comment>)', 'Primary')
            ->addOption('link-class', null, InputOption::VALUE_REQUIRED, 'Publishable navigation link component with a <comment>label</comment> and a <comment>route</comment>', 'App\\Entity\\NavigationLink');
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

        if (null === $this->linkClass($input)) {
            $linkClass = $io->ask('Navigation link component class, with a <comment>label</comment> and a <comment>route</comment> (leave empty for none)', null);
            $input->setOption('link-class', $linkClass ?? '');
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

        $linkClass = $this->linkClass($input);
        if (null === $linkClass && '' !== (string) $input->getOption('link-class')) {
            $io->note(\sprintf('%s is not a component class with a label and a route, so the scaffold has no navigation link.', $input->getOption('link-class')));
        }
        $linkShortName = null === $linkClass ? null : array_last(explode('\\', $linkClass));
        $namer = new VariableNamer();
        $useStatements = new UseStatementGenerator(null === $linkClass
            ? [AbstractCwaScaffold::class, CwaFixtureBuilder::class]
            : [AbstractCwaScaffold::class, CwaFixtureBuilder::class, $linkClass]);

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__ . '/../Resources/skeleton/scaffold/Scaffold.tpl.php',
            [
                'use_statements' => $useStatements,
                'layout_ref' => $layoutRef,
                'layout_component' => $layoutComponent,
                'layout_method' => $namer->method('Layout', (string) $layoutRef),
                'link_class' => $linkShortName,
                'link_variable' => null === $linkShortName ? null : $namer->variable($linkShortName, 'Home'),
                'link_label' => null === $linkClass ? null : $this->labelAssignment($linkClass),
                'publish_link' => null !== $linkClass && $this->publishableAttributeReader->isConfigured($linkClass),
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
            '<fg=yellow>Tip:</> add a private method for each layout and page, and call it from <comment>build()</comment>.',
            'Link a component to a route by its name with <comment>$cwa->component($link)->linkRoute(\'route\', routeName: \'home\')</comment>.',
        ]);
    }

    /**
     * @return class-string<AbstractComponent>|null
     */
    private function linkClass(InputInterface $input): ?string
    {
        $class = ltrim((string) $input->getOption('link-class'), '\\');
        if ('' === $class || !class_exists($class) || !is_a($class, AbstractComponent::class, true)) {
            return null;
        }

        return null !== $this->labelAssignment($class) && $this->isWritable($class, 'route') ? $class : null;
    }

    private function labelAssignment(string $class): ?string
    {
        if ($this->isPublicProperty($class, 'label')) {
            return "->label = 'Home'";
        }

        return method_exists($class, 'setLabel') ? "->setLabel('Home')" : null;
    }

    private function isWritable(string $class, string $property): bool
    {
        return $this->isPublicProperty($class, $property) || method_exists($class, 'set' . ucfirst($property));
    }

    private function isPublicProperty(string $class, string $property): bool
    {
        if (!property_exists($class, $property)) {
            return false;
        }
        $reflection = new \ReflectionProperty($class, $property);

        return $reflection->isPublic() && !$reflection->isReadOnly() && !$reflection->isStatic() && !$reflection->isPrivateSet() && !$reflection->isProtectedSet();
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
