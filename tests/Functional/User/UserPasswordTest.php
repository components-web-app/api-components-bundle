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

final class UserPasswordTest extends FunctionalTestCase
{
    use UserTrait;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidPasswords(): iterable
    {
        yield 'shorter than 6 characters' => ['abc', 'Your password must be more than 6 characters long.'];
        yield 'over 4096 characters' => [str_repeat('a', 4097), 'Your password cannot be over 4096 characters'];
    }

    #[DataProvider('invalidPasswords')]
    public function test_an_invalid_password_set_through_the_api_is_refused_and_not_saved(string $newPassword, string $message): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $user = self::loginAsUserWithPassword($client, $password);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['oldPassword' => $password, 'plainPassword' => $newPassword]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'plainPassword');
        self::assertJsonNodeSame('violations[0].message', $message);
        self::assertJsonNodeCount('violations', 1);
        self::assertPasswordIs($password, 'new_user');
        self::assertNoEmailSent();
    }

    public function test_a_super_admin_cannot_create_a_user_with_a_short_password(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);

        self::requestJsonLd($client, 'POST', '/users', ['username' => 'created_user', 'emailAddress' => 'created@example.com', 'plainPassword' => 'abc']);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'plainPassword');
        self::assertSame(0, self::countUsersWithUsername('created_user'));
    }

    public function test_a_valid_password_set_through_the_api_is_saved_hashed(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $newPassword = self::generatedSecret();
        $user = self::loginAsUserWithPassword($client, $password);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['oldPassword' => $password, 'plainPassword' => $newPassword]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('plainPassword');
        self::assertPasswordIs($newPassword, 'new_user');
        self::assertEmailOfTypeSent('password_changed');
    }

    public function test_setting_a_password_through_the_api_does_not_verify_the_email_address(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $newPassword = self::generatedSecret();
        $user = self::createUserWithRole('unverified_user', self::generatedSecret(), 'ROLE_USER', 'unverified@example.com');
        self::unverifyEmail($user);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['plainPassword' => $newPassword]);

        self::assertResponseStatusCodeSame(200);
        self::assertPasswordIs($newPassword, 'unverified_user');
        self::assertEmailAddressVerified('unverified_user', false);
    }
}
