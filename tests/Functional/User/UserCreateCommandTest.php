<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\User;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class UserCreateCommandTest extends FunctionalTestCase
{
    use ConsoleTrait;
    use UserTrait;

    public function test_a_valid_new_user_is_created(): void
    {
        $tester = self::createUserFromTheConsole('console_user', 'console@example.com');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Created user: console_user', $tester->getDisplay());
        self::assertSame(1, self::countUsersWithUsername('console_user'));
    }

    public function test_a_duplicate_username_fails_prints_the_violation_and_writes_no_second_user(): void
    {
        static::bootKernel();
        self::createUserWithRole('console_user', self::generatedSecret(), 'ROLE_USER', 'console@example.com');

        $tester = self::createUserFromTheConsole('console_user', 'other@example.com');

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('Sorry, that user already exists in the database.', $tester->getDisplay());
        self::assertSame(1, self::countUsersWithUsername('console_user'));
    }

    public function test_an_invalid_email_address_fails_and_writes_no_user(): void
    {
        $tester = self::createUserFromTheConsole('console_user', 'not-an-email');

        self::assertSame(Command::FAILURE, $tester->getStatusCode(), $tester->getDisplay());
        self::assertStringContainsString('emailAddress', $tester->getDisplay());
        self::assertSame(0, self::countUsersWithUsername('console_user'));
    }

    public function test_overwriting_an_existing_user_still_succeeds(): void
    {
        static::bootKernel();
        self::createUserWithRole('console_user', self::generatedSecret(), 'ROLE_USER', 'console@example.com');

        $tester = self::createUserFromTheConsole('console_user', 'changed@example.com', true);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        self::assertSame(1, self::countUsersWithUsername('console_user'));
        self::assertSame('changed@example.com', self::findUserByUsername('console_user')?->getEmailAddress());
    }

    private static function createUserFromTheConsole(string $username, string $email, bool $overwrite = false): CommandTester
    {
        return self::runCommand('silverback:api-components:user:create', [
            'username' => $username,
            'email' => $email,
            'password' => self::generatedSecret(),
            '--overwrite' => $overwrite,
        ]);
    }
}
