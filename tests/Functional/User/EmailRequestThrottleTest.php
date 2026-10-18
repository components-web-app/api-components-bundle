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

use ApiPlatform\Test\Client;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\CacheControlTrait;

final class EmailRequestThrottleTest extends FunctionalTestCase
{
    use CacheControlTrait;
    use UserTrait;

    public function test_a_password_reset_request_inside_its_throttle_is_too_many_requests_and_changes_nothing(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername('test.user@example.com');
        self::givePasswordResetToken($user, $token, '-1 hour');

        self::requestEmail($client, '/password/reset/request/my_username');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderIsSecondsBetween('retry-after', 82700, 82800);
        self::assertCacheControlContainsPrivate();
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('password reset', 'my_username', $token);
    }

    public function test_a_password_reset_request_after_its_throttle_sends_the_email(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername('test.user@example.com');
        self::givePasswordResetToken($user, self::generatedSecret(), '-25 hours');

        self::requestEmail($client, '/password/reset/request/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('password_reset', 'test.user@example.com');
    }

    public function test_a_resend_of_the_email_verification_inside_its_throttle_is_too_many_requests_and_changes_nothing(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername();
        self::unverifyEmail($user, $token);
        self::setEmailVerificationRequestedAt($user, '-2 minutes');

        self::requestEmail($client, '/resend-verify-email/my_username');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderIsSecondsBetween('retry-after', 170, 180);
        self::assertCacheControlContainsPrivate();
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('email verification', 'my_username', $token);
    }

    public function test_a_resend_of_the_email_verification_after_its_throttle_sends_the_email(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername();
        self::unverifyEmail($user, self::generatedSecret());
        self::setEmailVerificationRequestedAt($user, '-6 minutes');

        self::requestEmail($client, '/resend-verify-email/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('verify_email');
    }

    public function test_a_resend_of_the_new_email_address_confirmation_inside_its_throttle_is_too_many_requests_and_changes_nothing(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername();
        self::giveNewEmailAddress($user, 'new@example.com', $token, '-2 minutes');

        self::requestEmail($client, '/resend-verify-new-email/my_username');

        self::assertResponseStatusCodeSame(429);
        self::assertResponseHeaderIsSecondsBetween('retry-after', 170, 180);
        self::assertCacheControlContainsPrivate();
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('new email confirmation', 'my_username', $token);
    }

    public function test_a_resend_of_the_new_email_address_confirmation_after_its_throttle_sends_the_email(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername();
        self::giveNewEmailAddress($user, 'new@example.com', self::generatedSecret(), '-6 minutes');

        self::requestEmail($client, '/resend-verify-new-email/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('change_email_confirmation');
    }

    public function test_the_new_email_address_confirmation_is_not_throttled_by_a_recent_password_reset_request(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername();
        self::givePasswordResetToken($user, self::generatedSecret(), '-10 minutes');
        self::giveNewEmailAddress($user, 'new@example.com', self::generatedSecret(), '-10 minutes');

        self::requestEmail($client, '/resend-verify-new-email/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('change_email_confirmation');
    }

    public function test_the_email_verification_is_not_throttled_by_a_recent_password_reset_request(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername();
        self::givePasswordResetToken($user, self::generatedSecret(), '-10 minutes');
        self::unverifyEmail($user, self::generatedSecret());
        self::setEmailVerificationRequestedAt($user, '-10 minutes');

        self::requestEmail($client, '/resend-verify-email/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('verify_email');
    }

    public function test_a_password_reset_request_is_not_throttled_by_a_recent_new_email_address_confirmation(): void
    {
        $client = static::createClient();
        $user = self::createMyUsername();
        self::giveNewEmailAddress($user, 'new@example.com', self::generatedSecret(), '-1 minute');
        self::setEmailVerificationRequestedAt($user, '-1 minute');

        self::requestEmail($client, '/password/reset/request/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('password_reset');
    }

    #[DataProvider('unknownUserPaths')]
    public function test_an_unknown_user_is_still_not_found(string $path): void
    {
        $client = static::createClient();

        self::requestEmail($client, $path);

        self::assertResponseStatusCodeSame(404);
        self::assertNoEmailSent();
    }

    public static function unknownUserPaths(): iterable
    {
        yield 'password reset' => ['/password/reset/request/no_user'];
        yield 'email verification' => ['/resend-verify-email/no_user'];
        yield 'new email address confirmation' => ['/resend-verify-new-email/no_user'];
    }

    private static function createMyUsername(string $emailAddress = 'user@example.com'): User
    {
        return self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER', $emailAddress);
    }

    private static function requestEmail(Client $client, string $path): void
    {
        self::requestJsonLd($client, 'GET', $path, null, ['referer' => 'http://www.website.com']);
    }

    private static function assertCacheControlContainsPrivate(): void
    {
        self::assertStringContainsString('private', (string) self::getClient()->getResponse()->headers->get('cache-control'));
    }
}
