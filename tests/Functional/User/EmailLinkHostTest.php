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
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class EmailLinkHostTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_an_origin_header_that_is_not_allowed_is_refused_and_no_email_is_sent(): void
    {
        self::assertPasswordResetRequestIsRefused(['origin' => 'https://evil.example']);
    }

    public function test_a_referer_header_that_is_not_allowed_is_refused_and_no_email_is_sent(): void
    {
        self::assertPasswordResetRequestIsRefused(['referer' => 'https://evil.example/anything?x=1']);
    }

    public function test_an_origin_header_that_is_not_allowed_is_refused_even_when_the_referer_is_allowed(): void
    {
        self::assertPasswordResetRequestIsRefused(['referer' => 'http://www.website.com', 'origin' => 'https://evil.example:8443']);
    }

    public function test_the_port_is_part_of_the_origin_so_an_allowed_host_on_another_port_is_refused(): void
    {
        self::assertPasswordResetRequestIsRefused(['origin' => 'http://www.website.com:8080']);
    }

    public function test_an_allowed_origin_is_matched_against_the_whole_origin_not_a_substring_of_it(): void
    {
        self::assertPasswordResetRequestIsRefused(['origin' => 'http://www.website.com.evil.example']);
    }

    public function test_an_allowed_origin_with_a_port_is_used_with_its_port(): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['origin' => 'https://app.website.com:8443']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('https://app.website.com:8443/reset-password/my_username/');
    }

    public function test_an_allowed_origin_is_used(): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['origin' => 'http://www.website.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('http://www.website.com/reset-password/my_username/');
    }

    #[DataProvider('redirectsThatAreNotPlainRelativePaths')]
    public function test_a_redirect_path_query_value_that_is_not_a_plain_relative_path_falls_back_to_the_configured_path(string $redirect): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username?password_redirect=' . $redirect, null, ['referer' => 'http://www.website.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('http://www.website.com/reset-password/my_username/');
    }

    public static function redirectsThatAreNotPlainRelativePaths(): iterable
    {
        yield 'an absolute URL' => ['https://evil.example/steal/{{ username }}/{{ token }}'];
        yield 'a protocol-relative URL' => ['//evil.example/{{ token }}'];
        yield 'an encoded backslash after the slash' => ['/%5Cevil.example/{{ token }}'];
        yield 'two encoded backslashes' => ['%5C%5Cevil.example/{{ token }}'];
        yield 'an encoded tab' => ['/%09/evil.example/{{ token }}'];
        yield 'a double slash inside the path' => ['/path//evil.example/{{ token }}'];
        yield 'a javascript URL' => ['javascript:alert(1)'];
        yield 'a path with no leading slash' => ['relative/{{ token }}'];
    }

    public function test_a_relative_redirect_path_query_value_is_still_used(): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username?password_redirect=/another-path/{{ username }}/{{ token }}', null, ['referer' => 'http://www.website.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('http://www.website.com/another-path/my_username/');
    }

    public function test_a_resent_email_verification_with_an_origin_header_that_is_not_allowed_is_refused(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername();
        self::unverifyEmail($user, $token);

        self::requestJsonLd($client, 'GET', '/resend-verify-email/my_username', null, ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(400);
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('email verification', 'my_username', $token);
    }

    public function test_a_resent_new_email_address_confirmation_with_an_origin_header_that_is_not_allowed_is_refused(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername('user@example.com');
        self::giveNewEmailAddress($user, 'new@example.com', $token, '-1 day');

        self::requestJsonLd($client, 'GET', '/resend-verify-new-email/my_username', null, ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(400);
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('new email confirmation', 'my_username', $token);
    }

    public function test_a_registration_with_an_origin_header_that_is_not_allowed_is_saved_sends_no_welcome_email_and_logs_the_refusal(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserRegisterType::class);
        $password = self::generatedSecret();

        self::requestJsonLd($client, 'POST', $formIri . '/submit', [
            'user_register' => [
                'username' => 'new_user',
                'emailAddress' => 'user@example.com',
                'plainPassword' => ['first' => $password, 'second' => $password],
            ],
        ], ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(1, self::countUsersWithUsername('new_user'));
        self::assertNoEmailSent();
        self::assertRefusedEmailLinkLogged('new_user');
    }

    public function test_with_no_origin_or_referer_header_the_configured_default_origin_is_used(): void
    {
        $client = static::createClient();
        self::setEmailLinkDefaultOrigin('https://default.website.com');
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username');

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('https://default.website.com/reset-password/my_username/');
    }

    public function test_an_origin_header_that_is_not_allowed_falls_back_to_the_configured_default_origin(): void
    {
        $client = static::createClient();
        self::setEmailLinkDefaultOrigin('https://default.website.com');
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('https://default.website.com/reset-password/my_username/');
    }

    public function test_an_allowed_origin_header_is_preferred_to_the_configured_default_origin(): void
    {
        $client = static::createClient();
        self::setEmailLinkDefaultOrigin('https://default.website.com');
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['origin' => 'http://www.website.com']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('http://www.website.com/reset-password/my_username/');
    }

    public function test_with_no_origin_or_referer_header_and_no_default_origin_the_request_is_refused(): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username');

        self::assertResponseStatusCodeSame(400);
        self::assertNoEmailSent();
    }

    public function test_the_login_link_in_the_account_enabled_email_uses_the_default_origin_when_the_origin_header_is_not_allowed(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::setEmailLinkDefaultOrigin('https://default.website.com');
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');
        self::disableUser($user);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['enabled' => true], ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(200);
        self::assertSentEmailLinkStartsWith('https://default.website.com/login');
    }

    public function test_a_refused_password_reset_request_leaves_the_users_existing_reset_token_in_place(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createMyUsername();
        self::givePasswordResetToken($user, $token, '-2 days');

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(400);
        self::assertNoEmailSent();
        self::assertUserTokenStillIs('password reset', 'my_username', $token);
    }

    public function test_enabling_an_account_with_an_origin_header_that_is_not_allowed_is_saved_sends_no_email_and_logs_the_refusal(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('user@user.co', self::generatedSecret(), 'ROLE_USER');
        self::disableUser($user);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['enabled' => true], ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('enabled', true);
        self::assertNoEmailSent();
        self::assertRefusedEmailLinkLogged('user@user.co');
    }

    public function test_an_email_change_request_with_an_origin_header_that_is_not_allowed_is_saved_sends_no_email_and_logs_the_refusal(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['new_email_address' => ['newEmailAddress' => 'new@example.com']], ['origin' => 'https://evil.example']);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertNoEmailSent();
        self::assertRefusedEmailLinkLogged('new_user');
    }

    private static function createMyUsername(string $emailAddress = 'test.user@example.com'): User
    {
        return self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER', $emailAddress);
    }

    /**
     * @param array<string, string> $headers
     */
    private static function assertPasswordResetRequestIsRefused(array $headers): void
    {
        $client = static::createClient();
        self::createMyUsername();

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, $headers);

        self::assertResponseStatusCodeSame(400);
        self::assertNoEmailSent();
    }
}
