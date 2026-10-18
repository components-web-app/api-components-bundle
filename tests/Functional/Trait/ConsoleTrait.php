<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

trait ConsoleTrait
{
    /**
     * @param array<string, mixed> $input
     */
    protected static function runCommand(string $name, array $input = [], int $verbosity = OutputInterface::VERBOSITY_NORMAL): CommandTester
    {
        $tester = new CommandTester((new Application(static::$kernel ?? static::bootKernel()))->find($name));
        $tester->execute($input, ['interactive' => false, 'verbosity' => $verbosity]);
        static::getEntityManager()->clear();

        return $tester;
    }

    /**
     * @param array<string, mixed> $input
     */
    protected static function runApplication(array $input): ApplicationTester
    {
        $application = new Application(static::$kernel ?? static::bootKernel());
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $tester = new ApplicationTester($application);
        $tester->run($input, ['interactive' => false]);
        static::getEntityManager()->clear();

        return $tester;
    }
}
