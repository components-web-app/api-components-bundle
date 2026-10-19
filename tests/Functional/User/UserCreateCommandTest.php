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

use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * @param array<string, bool> $options
     * @param list<string>        $roles
     */
    #[DataProvider('createdUsers')]
    public function test_a_created_user_has_the_roles_and_enabled_state_of_the_options_and_a_verified_email_address(array $options, array $roles, bool $enabled): void
    {
        $password = self::generatedSecret();

        $tester = self::runConsoleCommand('silverback:api-components:user:create', ['username' => 'console_user', 'email' => 'console@example.com', 'password' => $password] + $options);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), $tester->getDisplay());
        $user = self::findUserByUsername('console_user');
        self::assertNotNull($user);
        self::assertSame($roles, $user->getRoles());
        self::assertSame($enabled, $user->isEnabled());
        self::assertTrue($user->isEmailAddressVerified());
        self::assertSame('console@example.com', $user->getEmailAddress());
        self::assertPasswordIs($password, 'console_user');
    }

    public static function createdUsers(): iterable
    {
        yield 'no options' => [[], ['ROLE_USER'], true];
        yield 'an admin' => [['--admin' => true], ['ROLE_ADMIN'], true];
        yield 'a super admin' => [['--super-admin' => true], ['ROLE_SUPER_ADMIN'], true];
        yield 'a super admin also given admin' => [['--super-admin' => true, '--admin' => true], ['ROLE_SUPER_ADMIN'], true];
        yield 'an inactive user' => [['--inactive' => true], ['ROLE_USER'], false];
        yield 'an inactive admin' => [['--inactive' => true, '--admin' => true], ['ROLE_ADMIN'], false];
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
        return self::runConsoleCommand('silverback:api-components:user:create', [
            'username' => $username,
            'email' => $email,
            'password' => self::generatedSecret(),
            '--overwrite' => $overwrite,
        ]);
    }
}
