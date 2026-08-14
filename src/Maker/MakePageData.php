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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Exception\RuntimeCommandException;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Util\UseStatementGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

final class MakePageData extends AbstractMaker
{
    public const DEFAULT_PROPERTY_TYPE = '?string';

    public static function getCommandName(): string
    {
        return 'make:page-data';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new CWA PageData entity';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The class name for the page data entity (e.g. <fg=yellow>ConferenceData</>)')
            ->addOption('properties', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Property definitions in <comment>name:type</comment> format. Repeat the flag, or pass one comma-separated list. Omitting <comment>:type</comment> defaults to <comment>' . self::DEFAULT_PROPERTY_TYPE . '</comment>', [])
            ->addUsage('ConferenceData --properties headline:?string,body:?string')
            ->addUsage('ConferenceData --properties headline:?string --properties body:?string')
            ->setHelp(<<<HELP
                The <info>%command.name%</info> command creates a page data entity extending <comment>AbstractPageData</comment>.

                Properties may be given as one comma-separated list:

                  <info>php %command.full_name% ConferenceData --properties headline:?string,body:?string</info>

                ...or by repeating the flag:

                  <info>php %command.full_name% ConferenceData --properties headline:?string --properties body:?string</info>

                <fg=yellow>Note:</> each <comment>--properties</comment> flag only takes a single value, so
                <comment>--properties headline:?string body:?string</comment> (space separated, one flag) is read as an extra
                command argument and fails with "Too many arguments". Use a comma or repeat the flag.

                Run the command with no <comment>--properties</comment> option to be prompted for each property.
                HELP);
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if ([] !== $input->getOption('properties')) {
            return;
        }

        $io->text([
            'Add the properties for this page data entity.',
            'Each one may be bound to a dynamic page data position in a template group.',
        ]);

        $properties = [];
        while (true) {
            $propertyName = $io->ask('New property name (press <return> to stop adding properties)');
            if (!$propertyName) {
                break;
            }
            $propertyType = $io->ask(\sprintf('Property type for "%s"', $propertyName), self::DEFAULT_PROPERTY_TYPE);
            $properties[] = $propertyName . ':' . $propertyType;
        }

        $input->setOption('properties', $properties);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $properties = $this->parseProperties($input->getOption('properties'));

        $classNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Entity\\PageData\\'
        );

        $useStatements = new UseStatementGenerator([
            'ApiPlatform\\Metadata\\ApiResource',
            ['Doctrine\\ORM\\Mapping' => 'ORM'],
            AbstractPageData::class,
        ]);

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__ . '/../Resources/skeleton/page_data/PageData.tpl.php',
            [
                'use_statements' => $useStatements,
                'properties' => $properties,
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $shortName = $classNameDetails->getShortName();
        $propertyNames = array_column($properties, 'name');

        if (!$propertyNames) {
            $io->warning([
                'No properties were defined.',
                \sprintf('%s only has the inherited page data fields.', $shortName),
                \sprintf('Add some with --properties headline:%s', self::DEFAULT_PROPERTY_TYPE),
            ]);
        }

        // The module's config type is `properties?: { [propertyName: string]: string }` — a map of
        // property name to the label shown in the admin, not a list of property names.
        $propertiesLines = $propertyNames ? array_merge(
            ['    properties: {'],
            array_map(fn (string $propName) => '      ' . $propName . ': \'' . $this->humanize($propName) . '\',', $propertyNames),
            ['    },']
        ) : ['    properties: {},'];

        $io->text(array_merge(
            [
                'Next: run <comment>php bin/console make:migration</comment> to generate the database migration.',
                '<fg=yellow>Always review the generated migration</> before running it.',
                '',
                'Add the following to your <comment>nuxt.config.ts</comment> under <comment>cwa.pageData</comment>:',
                '',
                '  ' . $shortName . ': {',
                '    name: \'' . $this->humanize($shortName) . '\',',
            ],
            $propertiesLines,
            [
                '  },',
                '',
                'Fixture scaffold stub:',
                '',
                '  $cwa->pageData(new ' . $shortName . '(), template: \'my-template\');',
            ]
        ));

        if ($propertyNames) {
            $io->text([
                '',
                'To use a property as a dynamic page data slot in a template group:',
            ]);
            foreach ($propertyNames as $propName) {
                $io->text('  ->pageDataPosition(' . $shortName . '::class, \'' . $propName . '\')');
            }
        }
    }

    /**
     * @param array<int, string> $rawProperties
     *
     * @return array<int, array{name: string, type: string, nullable: bool}>
     */
    private function parseProperties(array $rawProperties): array
    {
        $properties = [];
        foreach ($rawProperties as $rawProperty) {
            foreach (explode(',', $rawProperty) as $definition) {
                $definition = trim($definition);
                if ('' === $definition) {
                    continue;
                }

                [$propName, $propType] = array_pad(explode(':', $definition, 2), 2, self::DEFAULT_PROPERTY_TYPE);
                $propName = trim($propName);
                $propType = trim($propType);

                if ('' === $propName) {
                    throw new RuntimeCommandException(\sprintf('Invalid property definition "%s". Expected "name:type", e.g. "headline:%s".', $definition, self::DEFAULT_PROPERTY_TYPE));
                }
                if ('' === $propType) {
                    $propType = self::DEFAULT_PROPERTY_TYPE;
                }

                $properties[] = [
                    'name' => $propName,
                    'type' => $propType,
                    'nullable' => str_starts_with($propType, '?'),
                ];
            }
        }

        return $properties;
    }

    /**
     * Turns a camelCase / snake_case identifier into a human readable label.
     * e.g. "heroImage" and "hero_image" both become "Hero Image".
     */
    private function humanize(string $identifier): string
    {
        $words = preg_split('/(?<=[a-z0-9])(?=[A-Z])|[_\-\s]+/', $identifier) ?: [];
        $words = array_filter($words, static fn (string $word) => '' !== $word);

        return implode(' ', array_map(static fn (string $word) => ucfirst($word), $words));
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
