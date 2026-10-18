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
use Silverback\ApiComponentsBundle\Form\Type\User\PasswordUpdateType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class ForgotPasswordTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_i_can_request_a_new_password(): void
    {
        $client = static::createClient();
        self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username', null, self::referer());

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('password_reset', 'test.user@example.com');
    }

    public function test_i_get_the_appropriate_status_code_if_the_user_is_not_found(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/password/reset/request/no_user', null, self::referer());

        self::assertResponseStatusCodeSame(404);
    }

    public function test_i_can_request_a_new_password_with_a_custom_return_url(): void
    {
        $client = static::createClient();
        self::createUserWithRole('my_username', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'GET', '/password/reset/request/my_username?password_redirect=/another-path/{{ username }}/{{ token }}', null, self::referer());

        self::assertResponseStatusCodeSame(200);
        self::assertEmailOfTypeSent('custom_password_reset', 'test.user@example.com');
    }

    #[DataProvider('prefilledFields')]
    public function test_i_can_get_a_password_updated_form_with_pre_filled_fields(string $postfix, string $expectedUsername, string $expectedToken): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);

        self::requestJsonLd($client, 'GET', $formIri . $postfix, null, self::referer());

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('formView.children[0].vars.name', 'username');
        self::assertJsonNodeSame('formView.children[0].vars.value', $expectedUsername);
        self::assertJsonNodeSame('formView.children[1].vars.name', 'plainNewPasswordConfirmationToken');
        self::assertJsonNodeSame('formView.children[1].vars.value', $expectedToken);
    }

    public static function prefilledFields(): iterable
    {
        yield 'with a username and token in the query' => ['?username=abc&token=123', 'abc', '123'];
        yield 'with no query' => ['', '', ''];
    }

    public function test_i_can_reset_my_password_successfully(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);
        $token = self::generatedSecret();
        $user = self::createUserWithRole('username', self::generatedSecret(), 'ROLE_USER');
        self::givePasswordResetToken($user, $token, 'now');
        $newPassword = self::generatedSecret();

        self::submitPasswordUpdate($client, $formIri . '/submit', 'username', $token, $newPassword, $newPassword);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailOfTypeSent('password_changed', 'test.user@example.com');
    }

    public function test_i_can_still_reset_my_password_if_i_am_not_a_validated_user(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);
        $token = self::generatedSecret();
        $user = self::createUserWithRole('username', self::generatedSecret(), 'ROLE_USER');
        self::unverifyEmail($user, self::generatedSecret());
        self::givePasswordResetToken($user, $token, 'now');
        $newPassword = self::generatedSecret();

        self::submitPasswordUpdate($client, $formIri . '/submit', 'username', $token, $newPassword, $newPassword);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailOfTypeSent('password_changed', 'test.user@example.com');
        self::assertEmailAddressVerified('username');
    }

    #[DataProvider('passwordsWithAnInvalidToken')]
    public function test_i_cannot_reset_my_password_with_an_invalid_token(string $password, int $statusCode): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);
        self::givePasswordResetToken(self::createUsername(), self::generatedSecret(), 'now');

        self::submitPasswordUpdate($client, $formIri . '/submit', 'username', self::generatedSecret(), $password, $password);

        self::assertResponseStatusCodeSame($statusCode);
        self::assertNoEmailSent();
    }

    public static function passwordsWithAnInvalidToken(): iterable
    {
        yield 'a password that is too short' => [substr(bin2hex(random_bytes(1)), 0, 1), 422];
        yield 'a valid password' => [bin2hex(random_bytes(8)), 404];
    }

    #[DataProvider('invalidData')]
    public function test_i_cannot_reset_my_password_with_invalid_data(string $username, bool $sendToken, string $requestedAt, int $status): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);
        $token = self::generatedSecret();
        self::givePasswordResetToken(self::createUsername(), $token, $requestedAt);
        $newPassword = self::generatedSecret();

        self::submitPasswordUpdate($client, $formIri . '/submit', $username, $sendToken ? $token : '', $newPassword, $newPassword);

        self::assertResponseStatusCodeSame($status);
    }

    public static function invalidData(): iterable
    {
        yield 'an expired token' => ['username', true, '1970-01-01 00:00:00', 404];
        yield 'no username' => ['', true, 'now', 404];
        yield 'no token' => ['username', false, 'now', 404];
        yield 'no username and no token' => ['', false, 'now', 404];
        yield 'an unknown username and no token' => ['invalid', false, 'now', 404];
    }

    #[DataProvider('invalidPasswords')]
    public function test_i_should_receive_the_form_errors_on_an_invalid_password(string $passwordFirst, string $passwordSecond, string $message): void
    {
        $client = static::createClient();
        $formIri = self::createForm(PasswordUpdateType::class);
        $token = self::generatedSecret();
        self::givePasswordResetToken(self::createUsername(), $token, 'now');

        self::submitPasswordUpdate($client, $formIri . '/submit', 'username', $token, $passwordFirst, $passwordSecond);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonMatchesSchemaFile('form.schema.json');
        self::assertJsonNodeSame('formView.children[2].children[0].vars.errors[0]', $message);
    }

    public static function invalidPasswords(): iterable
    {
        $short = substr(bin2hex(random_bytes(1)), 0, 1);
        $valid = bin2hex(random_bytes(8));
        yield 'a password that is too short' => [$short, $short, 'Your password must be more than 6 characters long.'];
        yield 'no repeated password' => [$valid, '', 'The password fields must match.'];
        yield 'no first password' => ['', $valid, 'The password fields must match.'];
    }

    public function test_i_can_reset_my_password_successfully_without_a_specific_password_update_form_component_being_required(): void
    {
        $client = static::createClient();
        $token = self::generatedSecret();
        self::givePasswordResetToken(self::createUsername(), $token, 'now');
        $newPassword = self::generatedSecret();

        self::submitPasswordUpdate($client, '/component/forms/password_reset/submit', 'username', $token, $newPassword, $newPassword);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailOfTypeSent('password_changed', 'test.user@example.com');
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    /**
     * @return array<string, string>
     */
    private static function referer(): array
    {
        return ['referer' => 'http://www.website.com'];
    }

    private static function createUsername(): User
    {
        return self::createUserWithRole('username', self::generatedSecret(), 'ROLE_USER');
    }

    private static function submitPasswordUpdate(Client $client, string $url, string $username, string $token, string $first, string $second): void
    {
        self::requestJsonLd($client, 'POST', $url, [
            'password_update' => [
                'username' => $username,
                'plainNewPasswordConfirmationToken' => $token,
                'plainPassword' => ['first' => $first, 'second' => $second],
            ],
        ], self::referer());
    }
}
