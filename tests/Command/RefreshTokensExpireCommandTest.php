<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\ORM\EntityNotFoundException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\RefreshTokensExpireCommand;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\RefreshToken\Storage\RefreshTokenStorageInterface;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshTokensExpireCommandTest extends TestCase
{
    public function test_every_field_named_in_the_field_option_help_is_a_user_property(): void
    {
        $description = $this->command()->getDefinition()->getOption('field')->getDescription();

        self::assertSame(1, preg_match('/\(([^)]+)\)/', $description, $matches));
        foreach (array_map('trim', explode(',', $matches[1])) as $field) {
            self::assertTrue(property_exists(AbstractUser::class, $field), \sprintf('The --field help names "%s", which is not a property of %s', $field, AbstractUser::class));
        }
    }

    public function test_the_help_names_the_command_by_its_registered_name(): void
    {
        $command = $this->command();

        self::assertStringContainsString(\sprintf('The <info>%s</info> command', $command->getName()), $command->getProcessedHelp());
    }

    public function test_a_user_is_found_by_username_through_a_repository_that_is_not_doctrine(): void
    {
        $user = new User('alice', 'alice@example.com');
        $other = new User('bob', 'bob@example.com');

        $this->assertTokensExpiredFor($user, [$other, $user], ['username' => 'alice']);
    }

    public function test_a_user_is_found_by_email_address_through_a_repository_that_is_not_doctrine(): void
    {
        $user = new User('alice', 'alice@example.com');
        $other = new User('bob', 'bob@example.com');

        $this->assertTokensExpiredFor($user, [$other, $user], ['username' => 'alice@example.com', '--field' => 'emailAddress']);
    }

    public function test_a_username_matches_regardless_of_case(): void
    {
        $user = new User('Alice', 'alice@example.com');

        $this->assertTokensExpiredFor($user, [$user], ['username' => 'aLICE']);
    }

    public function test_a_username_that_is_another_users_email_address_is_not_found(): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::never())->method('expireAll');
        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository([new User('alice', 'alice@example.com')])));

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('User with username "alice@example.com" not found.');
        $tester->execute(['username' => 'alice@example.com']);
    }

    public function test_an_unknown_email_address_is_not_found(): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::never())->method('expireAll');
        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository([new User('alice', 'alice@example.com')])));

        $this->expectException(EntityNotFoundException::class);
        $this->expectExceptionMessage('User with emailAddress "alice" not found.');
        $tester->execute(['username' => 'alice', '--field' => 'emailAddress']);
    }

    public function test_an_unsupported_field_fails_naming_the_supported_fields_and_expires_nothing(): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::never())->method('expireAll');
        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository([])));

        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The field "id" is not supported. Use one of: username, emailAddress.');
        $tester->execute(['--field' => 'id']);
    }

    public function test_a_username_that_is_also_another_users_email_address_is_reported_as_ambiguous(): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::never())->method('expireAll');
        $users = [new User('alice@example.com', 'first@example.com'), new User('bob', 'alice@example.com')];
        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository($users)));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('More than one user matches "alice@example.com" as a username or email address, so the user with that username cannot be identified. No refresh-tokens were expired.');
        $tester->execute(['username' => 'alice@example.com']);
    }

    public function test_all_tokens_are_expired_when_no_user_is_given(): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::once())->method('expireAll')->with(null);
        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository([])));

        $tester->execute([]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('RefreshTokens for all users successfully expired.', $tester->getDisplay());
    }

    /**
     * @param AbstractUser[]       $users
     * @param array<string, mixed> $input
     */
    private function assertTokensExpiredFor(AbstractUser $expected, array $users, array $input): void
    {
        $storage = $this->createMock(RefreshTokenStorageInterface::class);
        $storage->expects(self::once())->method('expireAll')->with(self::identicalTo($expected));

        $tester = new CommandTester(new RefreshTokensExpireCommand($storage, new InMemoryUserRepository($users)));
        $tester->execute($input);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString(\sprintf('RefreshTokens for user %s successfully expired.', $input['username']), $tester->getDisplay());
    }

    private function command(): RefreshTokensExpireCommand
    {
        return new RefreshTokensExpireCommand($this->createStub(RefreshTokenStorageInterface::class), $this->createStub(UserRepositoryInterface::class));
    }
}
