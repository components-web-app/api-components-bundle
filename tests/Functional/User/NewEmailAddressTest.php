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
use Silverback\ApiComponentsBundle\Form\Type\User\NewEmailAddressType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class NewEmailAddressTest extends FunctionalTestCase
{
    use UserTrait;

    private const array REFERER = ['referer' => 'http://www.website.com'];
    private const string WRONG_PASSWORD = 'You have not entered your current password correctly. Please try again.';

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('successfulChangeRequests')]
    public function test_submit_a_successful_change_email_request(array $headers, string $postfix, string $expectedEmail): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::submitNewEmailAddress($client, $formIri . $postfix, 'new@example.com', $headers, 'password');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertJsonNodeSame('emailAddress', 'user@example.com');
        self::assertJsonNodeNotExists('newEmailConfirmationToken');
        self::assertJsonNodeNotExists('oldPassword');
        self::assertEmailChangeEmailsSent($expectedEmail);
    }

    public static function successfulChangeRequests(): iterable
    {
        yield 'an allowed origin' => [['origin' => 'http://www.website.com'], '/submit', 'change_email_confirmation'];
        yield 'an allowed referer' => [['referer' => 'http://www.website.com'], '/submit', 'change_email_confirmation'];
        yield 'an allowed referer and a custom redirect path' => [['referer' => 'http://www.website.com'], '/submit?email_redirect=/another-path/{{ username }}/{{ new_email }}/{{ token }}', 'custom_change_email_confirmation'];
    }

    /**
     * @param array<string, string> $headers
     */
    #[DataProvider('invalidOrMissingHeaders')]
    public function test_an_invalid_or_missing_referer_and_origin_still_saves_the_change_request_sends_only_the_notice_and_logs_the_refusal(array $headers): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::submitNewEmailAddress($client, $formIri . '/submit', 'new@example.com', $headers, 'password');

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertOnlyEmailChangeNoticeSent();
        self::assertRefusedEmailLinkLogged('new_user');
    }

    public static function invalidOrMissingHeaders(): iterable
    {
        yield 'an invalid referer' => [['referer' => 'invalid']];
        yield 'a referer with no scheme' => [['referer' => 'no-scheme.com:90/path']];
        yield 'an empty referer' => [['referer' => '']];
        yield 'an invalid origin' => [['origin' => 'invalid']];
        yield 'an origin with no scheme' => [['origin' => 'no-scheme.com:90/path']];
        yield 'an empty origin' => [['origin' => '']];
        yield 'no referer or origin' => [[]];
    }

    public function test_i_get_an_invalid_response_if_i_try_to_change_my_email_address_to_the_same_as_it_already_is(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::submitNewEmailAddress($client, $formIri . '/submit', 'user@example.com', self::REFERER, 'password');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.children[0].vars.errors[0]', 'Your new email address should be different.');
        self::assertJsonMatchesSchemaFile('form.schema.json');
        self::assertNoEmailSent();
    }

    public function test_i_get_an_invalid_response_if_i_try_to_change_my_email_address_to_one_that_already_exists(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);
        self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::submitNewEmailAddress($client, $formIri . '/submit', 'test.user@example.com', self::REFERER, 'password');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.children[0].vars.errors[0]', 'Someone else is already registered with that email address.');
        self::assertJsonMatchesSchemaFile('form.schema.json');
        self::assertNoEmailSent();
    }

    public function test_i_am_not_able_to_change_my_email_address_to_a_blank_string(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createForm(NewEmailAddressType::class);
        $user = self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => ''], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'newEmailAddress');
        self::assertJsonNodeSame('violations[0].message', 'This value should not be blank.');
        self::assertJsonMatchesSchemaFile('validation_errors_object.schema.json');
        self::assertNoEmailSent();
    }

    public function test_a_super_admin_can_set_a_users_new_email_address_to_null(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        self::createForm(NewEmailAddressType::class);
        $user = self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => null], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeNotExists('newEmailConfirmationToken');
        self::assertNoEmailSent();
    }

    public function test_i_can_cancel_an_existing_request_to_change_email_address_with_a_blank_string(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);
        self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::submitNewEmailAddress($client, $formIri . '/submit', '', self::REFERER);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeNotExists('newEmailConfirmationToken');
        self::assertNoEmailSent();
    }

    public function test_i_can_cancel_an_existing_request_to_change_email_address_with_null(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);
        self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');

        self::submitNewEmailAddress($client, $formIri . '/submit', null, self::REFERER);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonNodeNotExists('newEmailConfirmationToken');
        self::assertNoEmailSent();
    }

    public function test_cancelling_a_pending_request_to_change_email_address_clears_it_refuses_its_link_and_lets_a_new_change_start_at_once(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);
        $token = self::generatedSecret();
        self::giveNewEmailAddress($user, 'new@example.com', $token, 'now');

        self::submitNewEmailAddress($client, $formIri . '/submit', '', self::REFERER);

        self::assertResponseStatusCodeSame(201);
        self::assertNoEmailSent();
        $stored = self::findUserByUsername('new_user');
        self::assertNotNull($stored);
        self::assertNull($stored->getNewEmailAddress());
        self::assertNull($stored->getNewEmailConfirmationToken());

        self::requestJsonLd($client, 'GET', '/confirm-email/new_user/new@example.com/' . $token, null, self::REFERER);

        self::assertResponseStatusCodeSame(404);
        self::assertEmailAddressConfirmed('user@example.com', 'new_user');

        $reloaded = self::findUserByUsername('new_user');
        self::assertNotNull($reloaded);
        self::addBearerToken($client, $reloaded);
        self::submitNewEmailAddress($client, $formIri . '/submit', 'new@example.com', self::REFERER, 'password');

        self::assertResponseStatusCodeSame(201);
        self::assertEmailChangeEmailsSent();
    }

    public function test_i_can_resend_a_new_email_address_confirmation_email_with_a_new_token(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        self::createForm(NewEmailAddressType::class);
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER', 'user@example.com');
        self::giveNewEmailAddress($user, 'new@example.com', self::generatedSecret(), '-1 day');

        self::requestJsonLd($client, 'GET', '/resend-verify-new-email/my_username', null, self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('change_email_confirmation', 'new@example.com');
    }

    public function test_requesting_a_new_email_address_confirmation_for_an_unknown_username_is_a_404_not_an_error(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/resend-verify-new-email/no_user', null, self::REFERER);

        self::assertResponseStatusCodeSame(404);
        self::assertNoEmailSent();
    }

    public function test_i_can_verify_my_new_email_address(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        $user = self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER', 'old@email.com');
        self::giveNewEmailAddress($user, 'new@email.com', $token);

        self::requestJsonLd($client, 'GET', '/confirm-email/my_username/new@email.com/' . $token, null, self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertEmailAddressConfirmed('new@email.com', 'my_username');
        self::assertEmailAddressVerified('my_username');
        self::assertNoEmailSent();
    }

    public function test_email_verification_is_reset_if_another_user_now_has_confirmed_the_email_address_this_user_is_trying_to_confirm(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        self::createUserWithRole('new@email.com', self::generatedSecret(), 'ROLE_USER', 'new@email.com');
        $user = self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');
        self::giveNewEmailAddress($user, 'new@email.com', $token);

        self::requestJsonLd($client, 'GET', '/confirm-email/another_user/new@email.com/' . $token);

        self::assertResponseStatusCodeSame(401);
        self::assertEmailAddressConfirmed('test.user@example.com', 'another_user');
        self::assertNoEmailSent();
    }

    public function test_a_change_request_without_the_current_password_is_refused_and_changes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::submitNewEmailAddress($client, $formIri . '/submit', 'new@example.com', self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.children[1].vars.full_name', 'new_email_address[oldPassword]');
        self::assertJsonNodeSame('formView.children[1].vars.errors[0]', self::WRONG_PASSWORD);
        self::assertNoEmailSent();
        self::assertNoNewEmailAddress('new_user');
    }

    public function test_a_change_request_with_a_wrong_current_password_is_refused_and_changes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $formIri = self::createForm(NewEmailAddressType::class);

        self::submitNewEmailAddress($client, $formIri . '/submit', 'new@example.com', self::REFERER, 'not-my-password');

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.children[1].vars.errors[0]', self::WRONG_PASSWORD);
        self::assertNoEmailSent();
        self::assertNoNewEmailAddress('new_user');
    }

    public function test_my_own_patch_of_a_new_email_address_needs_my_current_password(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => 'new@example.com'], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'oldPassword');
        self::assertJsonNodeSame('violations[0].message', self::WRONG_PASSWORD);
        self::assertNoEmailSent();
        self::assertNoNewEmailAddress('new_user');
    }

    public function test_my_own_patch_of_a_new_email_address_with_my_current_password_sends_the_confirmation_to_the_new_address_and_a_notice_to_the_current_one(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => 'new@example.com', 'oldPassword' => 'password'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertJsonNodeSame('emailAddress', 'user@example.com');
        self::assertEmailChangeEmailsSent();
    }

    public function test_a_super_admin_changing_another_users_new_email_address_enters_their_own_password(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $userPassword = self::generatedSecret();
        $user = self::createUserWithRole('another_user', $userPassword, 'ROLE_USER');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => 'new@example.com', 'oldPassword' => $userPassword], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'oldPassword');
        self::assertNoNewEmailAddress('another_user');

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['newEmailAddress' => 'new@example.com', 'oldPassword' => 'password'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertEmailChangeEmailsSent(currentEmailAddress: 'test.user@example.com');
    }

    public function test_a_write_that_leaves_a_pending_new_email_address_unchanged_needs_no_password(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $user = self::createUserWithRole('another_user', self::generatedSecret(), 'ROLE_USER');
        self::giveNewEmailAddress($user, 'new@example.com', self::generatedSecret());

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['username' => 'renamed_user', 'newEmailAddress' => 'new@example.com'], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('newEmailAddress', 'new@example.com');
        self::assertEmailOfTypeSent('username_changed_notification', 'test.user@example.com');
    }

    private static function assertNoNewEmailAddress(string $username): void
    {
        $stored = self::findUserByUsername($username);
        self::assertNotNull($stored);
        self::assertNull($stored->getNewEmailAddress());
        self::assertNull($stored->getNewEmailConfirmationToken());
    }

    /**
     * @param array<string, string> $headers
     */
    private static function submitNewEmailAddress(Client $client, string $url, ?string $newEmailAddress, array $headers, ?string $oldPassword = null): void
    {
        $data = ['newEmailAddress' => $newEmailAddress];
        if (null !== $oldPassword) {
            $data['oldPassword'] = $oldPassword;
        }
        self::requestJsonLd($client, 'POST', $url, ['new_email_address' => $data], $headers);
    }
}
