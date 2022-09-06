<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputAwareMakerInterface;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Maker\MakeEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

final class ApiResource extends AbstractMaker implements InputAwareMakerInterface
{
    private MakeEntity $makeEntity;

    public function __construct(MakeEntity $makeEntity)
    {
        $this->makeEntity = $makeEntity;
    }

    public static function getCommandName(): string
    {
        return 'make:api-component';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $this->makeEntity->configureCommand($command, $inputConfig);
    }

    public function configureDependencies(DependencyBuilder $dependencies, InputInterface $input = null): void
    {
        $this->makeEntity->configureDependencies($dependencies, $input);
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $this->makeEntity->generate($input, $io, $generator);
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        $input->setOption('api-resource', true);
        $this->makeEntity->interact($input, $io, $command);
    }
}
