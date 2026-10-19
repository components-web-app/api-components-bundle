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

final class NotificationEmailsTest extends FunctionalTestCase
{
    use UserTrait;

    private const array REFERER = ['referer' => 'http://www.website.com'];

    public function test_i_receive_an_email_when_my_user_has_been_enabled(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');
        self::disableUser($user);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['enabled' => true], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('enabled_notification', 'test.user@example.com');
    }

    public function test_i_do_not_receive_an_email_if_my_account_is_disabled(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['enabled' => false], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertNoEmailSent();
    }

    public function test_i_do_not_receive_an_email_if_the_account_was_already_enabled(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['enabled' => true], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertNoEmailSent();
    }

    public function test_i_receive_an_email_when_my_username_has_been_changed(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['username' => 'test.user@example.com'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('username_changed_notification', 'test.user@example.com');
    }

    public function test_i_receive_an_email_when_my_user_has_been_enabled_by_a_put(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');
        self::disableUser($user);

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['enabled' => true], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('enabled_notification', 'test.user@example.com');
    }

    public function test_i_receive_an_email_when_my_username_has_been_changed_by_a_put(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['username' => 'test.user@example.com'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('username_changed_notification', 'test.user@example.com');
    }

    public function test_a_put_that_changes_nothing_a_notification_is_about_sends_no_email(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PUT', self::iriOf($user), ['username' => 'my_username', 'enabled' => true], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertNoEmailSent();
    }
}
