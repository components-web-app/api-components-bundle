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

final class VerifyEmailAddressTest extends FunctionalTestCase
{
    use UserTrait;

    private const array REFERER = ['referer' => 'http://www.website.com'];

    public function test_a_user_with_an_unverified_email_is_not_able_to_login(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $user = self::createUserWithRole('user', $password, 'ROLE_USER');
        self::unverifyEmail($user);

        $response = self::requestJsonLd($client, 'POST', '/login', ['username' => 'user', 'password' => $password], ['x-auth-token' => self::generatedSecret()]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame([
            'code' => 401,
            'message' => 'Please verify your email address before logging in. If you did not receive a confirmation email please try resetting your password using the forgot password feature.',
        ], $response->toArray(false));
    }

    public function test_email_becomes_unverified_when_the_email_address_is_changed_by_the_api(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['emailAddress' => 'new@email.com'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('verify_email', 'new@email.com');
    }

    public function test_i_can_verify_my_email_address(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');
        self::unverifyEmail($user, $token);

        self::requestJsonLd($client, 'GET', '/verify-email/my_username/' . $token);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailAddressVerified('my_username');
    }

    public function test_i_can_request_a_new_email_address_verification_email(): void
    {
        $client = static::createClient();
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER', 'user@example.com');
        self::unverifyEmail($user);

        self::requestJsonLd($client, 'GET', '/resend-verify-email/my_username', null, self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('verify_email');
    }

    public function test_requesting_a_verification_email_for_an_unknown_username_is_a_404_not_an_error(): void
    {
        $client = static::createClient();
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');
        self::unverifyEmail($user);

        self::requestJsonLd($client, 'GET', '/resend-verify-email/no_user', null, self::REFERER);

        self::assertResponseStatusCodeSame(404);
        self::assertNoEmailSent();
    }

    #[DataProvider('incorrectParameters')]
    public function test_i_cannot_verify_email_addresses_with_incorrect_parameters(bool $correctUsername, bool $correctToken): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');
        self::unverifyEmail($user, $token);

        self::requestJsonLd($client, 'GET', \sprintf('/verify-email/%s/%s', $correctUsername ? 'my_username' : 'wrong', $correctToken ? $token : 'wrong'));

        self::assertResponseStatusCodeSame(404);
        self::assertEmailAddressVerified('my_username', false);
    }

    public static function incorrectParameters(): iterable
    {
        yield 'a wrong token' => [true, false];
        yield 'a wrong username' => [false, true];
    }
}
