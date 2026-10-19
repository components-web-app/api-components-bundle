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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ConsoleTrait;
use Symfony\Component\Console\Command\Command;

final class RefreshTokensExpireTest extends FunctionalTestCase
{
    use ConsoleTrait;
    use UserTrait;

    public function test_refresh_tokens_are_expired_only_for_a_user_found_by_username(): void
    {
        $user = self::createUserWithRefreshToken();
        $other = self::createOtherUserWithRefreshToken();

        $tester = self::runConsoleCommand('silverback:api-components:refresh-tokens:expire', ['username' => 'NEW_USER']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('RefreshTokens for user NEW_USER successfully expired.', trim($tester->getDisplay()));
        self::assertRefreshTokenExpired($user);
        self::assertRefreshTokenExpired($other, false);
    }

    public function test_refresh_tokens_are_expired_only_for_a_user_found_by_email_address(): void
    {
        $user = self::createUserWithRefreshToken();
        $other = self::createOtherUserWithRefreshToken();

        $tester = self::runConsoleCommand('silverback:api-components:refresh-tokens:expire', ['username' => 'user@example.com', '--field' => 'emailAddress']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('RefreshTokens for user user@example.com successfully expired.', trim($tester->getDisplay()));
        self::assertRefreshTokenExpired($user);
        self::assertRefreshTokenExpired($other, false);
    }

    public function test_with_no_username_the_refresh_tokens_of_every_user_are_expired(): void
    {
        $user = self::createUserWithRefreshToken();
        $other = self::createOtherUserWithRefreshToken();

        $tester = self::runConsoleCommand('silverback:api-components:refresh-tokens:expire');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame('RefreshTokens for all users successfully expired.', trim($tester->getDisplay()));
        self::assertRefreshTokenExpired($user);
        self::assertRefreshTokenExpired($other);
    }

    public function test_a_username_that_is_only_a_users_email_address_finds_nobody(): void
    {
        $user = self::createUserWithRefreshToken();

        self::assertCommandFailedWith('User with username "user@example.com" not found.', self::expireRefreshTokens('user@example.com'));
        self::assertRefreshTokenExpired($user, false);
    }

    public function test_a_username_that_is_also_another_users_email_address_is_reported_as_ambiguous(): void
    {
        $user = self::createUserWithRefreshToken();
        self::createUserWithRole('user@example.com', self::generatedSecret(), 'ROLE_USER', 'other@example.com');

        self::assertCommandFailedWith('cannot be identified. No refresh-tokens were expired.', self::expireRefreshTokens('user@example.com'));
        self::assertRefreshTokenExpired($user, false);
    }

    public function test_a_field_other_than_username_or_email_address_is_rejected(): void
    {
        $user = self::createUserWithRefreshToken();

        self::assertCommandFailedWith('Use one of: username, emailAddress.', self::expireRefreshTokens('new_user', 'id'));
        self::assertRefreshTokenExpired($user, false);
    }

    private static function createUserWithRefreshToken(): User
    {
        static::bootKernel();
        $user = self::createUser(['ROLE_USER']);
        self::createToken($user);
        self::createRefreshToken($user);

        return $user;
    }

    private static function createOtherUserWithRefreshToken(): User
    {
        $user = self::createUser(['ROLE_USER'], 'other_user', 'other@example.com');
        self::createToken($user);
        self::createRefreshToken($user);

        return $user;
    }

    private static function expireRefreshTokens(string $value, string $field = ''): ?\Throwable
    {
        $input = ['username' => $value];
        if ('' !== $field) {
            $input['--field'] = $field;
        }
        try {
            self::runConsoleCommand('silverback:api-components:refresh-tokens:expire', $input);
        } catch (\Throwable $exception) {
            return $exception;
        }

        return null;
    }

    private static function assertCommandFailedWith(string $text, ?\Throwable $exception): void
    {
        self::assertNotNull($exception, 'The command succeeded');
        self::assertStringContainsString($text, $exception->getMessage());
    }
}
