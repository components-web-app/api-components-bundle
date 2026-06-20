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
            ->addOption('properties', null, InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL, 'Property definitions in <comment>name:type</comment> format (e.g. <comment>headline:?string</comment>)', []);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $rawProperties = $input->getOption('properties');
        $properties = [];
        foreach ($rawProperties as $raw) {
            [$propName, $propType] = explode(':', $raw, 2);
            $properties[] = [
                'name' => $propName,
                'type' => $propType,
                'nullable' => str_starts_with($propType, '?'),
            ];
        }

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

        $io->text([
            'Next: run <comment>php bin/console make:migration</comment> to generate the database migration.',
            '<fg=yellow>Always review the generated migration</> before running it.',
            '',
            'Add the following to your <comment>nuxt.config.ts</comment> under <comment>cwa.pageData</comment>:',
            '',
            '  ' . $shortName . ': {',
            '    properties: [' . implode(', ', array_map(static fn ($p) => "'" . $p . "'", $propertyNames)) . '],',
            '  },',
            '',
            'Fixture scaffold stub:',
            '',
            '  $cwa->pageData(new ' . $shortName . '(), template: \'my-template\');',
        ]);

        if (!empty($propertyNames)) {
            $io->text([
                '',
                'To use a property as a dynamic page data slot in a template group:',
            ]);
            foreach ($propertyNames as $propName) {
                $io->text('  ->pageDataPosition(' . $shortName . '::class, \'' . $propName . '\')');
            }
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
