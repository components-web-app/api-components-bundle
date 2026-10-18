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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

final class ConsoleTraitTest extends FunctionalTestCase
{
    use ConsoleTrait;
    use ResourceTrait;

    public function test_a_command_runs_on_the_test_kernel_without_interaction(): void
    {
        $tester = self::runConsoleCommand('silverback:api-components:user:create', ['username' => 'console_user', 'email' => 'console@example.com', 'password' => 'password']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(1, self::countEntities(User::class));
        self::assertSame('console@example.com', self::getEntityManager()->getRepository(User::class)->findOneBy(['username' => 'console_user'])?->getEmailAddress());
    }

    public function test_a_command_can_run_verbosely(): void
    {
        $tester = self::runConsoleCommand('silverback:api-components:scan-orphaned', ['--no-notify' => true], OutputInterface::VERBOSITY_VERBOSE);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(OutputInterface::VERBOSITY_VERBOSE, $tester->getOutput()->getVerbosity());
    }

    public function test_an_application_run_dispatches_console_events(): void
    {
        $seen = [];
        self::bootKernel();
        self::getContainer()->get('event_dispatcher')->addListener('console.command', static function () use (&$seen): void {
            $seen[] = 'console.command';
        });

        $tester = self::runApplication(['command' => 'silverback:api-components:scan-orphaned', '--no-notify' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(['console.command'], $seen);
    }
}
