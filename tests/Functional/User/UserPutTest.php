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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPutTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_a_super_admin_put_with_only_a_username_keeps_the_users_other_fields(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $password = self::generatedSecret();
        $user = self::createUserWithRole('my_user', $password, 'ROLE_ADMIN', 'my_user@example.com');

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['username' => 'renamed_user']);

        self::assertResponseStatusCodeSame(200);
        self::assertUserKeptTheirFields('renamed_user', 'my_user@example.com', 'ROLE_ADMIN', $password);
    }

    public function test_a_super_admin_put_with_a_new_username_and_email_address_keeps_the_password_roles_and_verification(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $password = self::generatedSecret();
        $user = self::createUserWithRole('my_user', $password, 'ROLE_ADMIN', 'my_user@example.com');

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['username' => 'renamed_user', 'emailAddress' => 'renamed@example.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertUserKeptTheirFields('renamed_user', 'renamed@example.com', 'ROLE_ADMIN', $password);
    }

    public function test_a_users_own_put_with_only_a_new_email_address_keeps_their_other_fields(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $user = self::loginAsUserWithPassword($client, $password);

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['newEmailAddress' => 'changed@example.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertUserKeptTheirFields('new_user', 'user@example.com', 'ROLE_USER', $password);
    }

    private static function assertUserKeptTheirFields(string $username, string $emailAddress, string $role, string $password): void
    {
        $user = self::findUserByUsername($username);
        self::assertNotNull($user, \sprintf('The user `%s` does not exist', $username));
        self::assertSame($emailAddress, $user->getEmailAddress());
        self::assertSame([$role], $user->getRoles());
        self::assertNotEmpty($user->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $password), 'The password hash changed.');
        self::assertTrue($user->isEnabled(), 'The user is disabled.');
        self::assertTrue($user->isEmailAddressVerified(), 'The email address is unverified.');
    }
}
