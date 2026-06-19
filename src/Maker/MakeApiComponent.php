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

use Silverback\ApiComponentsBundle\Annotation as Silverback;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Utility\PublishableTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\TimestampedTrait;
use Silverback\ApiComponentsBundle\Entity\Utility\UploadableTrait;
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
use Symfony\Component\HttpFoundation\File\File;

final class MakeApiComponent extends AbstractMaker
{
    public static function getCommandName(): string
    {
        return 'make:api-component';
    }

    public static function getCommandDescription(): string
    {
        return 'Create a new CWA API component entity';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConf): void
    {
        $command
            ->addArgument('name', InputArgument::OPTIONAL, 'The class name for the component (e.g. <fg=yellow>HeroBlock</>)')
            ->addOption('timestamped', null, InputOption::VALUE_NONE, 'Add <comment>#[Timestamped]</comment> behaviour (createdAt / updatedAt)')
            ->addOption('publishable', null, InputOption::VALUE_NONE, 'Add <comment>#[Publishable]</comment> behaviour (draft/published lifecycle)')
            ->addOption('uploadable', null, InputOption::VALUE_NONE, 'Add <comment>#[Uploadable]</comment> behaviour (includes a file property)');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (!$input->getOption('timestamped')) {
            $input->setOption('timestamped', $io->confirm('Add <comment>#[Timestamped]</comment> behaviour (createdAt / updatedAt)?', false));
        }
        if (!$input->getOption('publishable')) {
            $input->setOption('publishable', $io->confirm('Add <comment>#[Publishable]</comment> behaviour (draft/published lifecycle)?', false));
        }
        if (!$input->getOption('uploadable')) {
            $input->setOption('uploadable', $io->confirm('Add <comment>#[Uploadable]</comment> behaviour (includes a file property)?', false));
        }
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $timestamped = (bool) $input->getOption('timestamped');
        $publishable = (bool) $input->getOption('publishable');
        $uploadable = (bool) $input->getOption('uploadable');

        $classNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Entity\\Component\\'
        );

        $useStatements = new UseStatementGenerator([
            'ApiPlatform\\Metadata\\ApiResource',
            ['Doctrine\\ORM\\Mapping' => 'ORM'],
            ['Silverback\\ApiComponentsBundle\\Annotation' => 'Silverback'],
            AbstractComponent::class,
        ]);

        if ($timestamped) {
            $useStatements->addUseStatement(TimestampedTrait::class);
        }
        if ($publishable) {
            $useStatements->addUseStatement(PublishableTrait::class);
        }
        if ($uploadable) {
            $useStatements->addUseStatement(UploadableTrait::class);
            $useStatements->addUseStatement(File::class);
        }

        $generator->generateClass(
            $classNameDetails->getFullName(),
            __DIR__.'/../Resources/skeleton/component/Component.tpl.php',
            [
                'use_statements' => $useStatements,
                'timestamped' => $timestamped,
                'publishable' => $publishable,
                'uploadable' => $uploadable,
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: run <comment>php bin/console make:migration</comment> to generate the migration for your new component.',
            '<fg=yellow>Always review the generated migration</> before running it — adjust column types, lengths, or indexes to match your requirements.',
        ]);
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }
}
