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
use Silverback\ApiComponentsBundle\Form\Type\User\ChangePasswordType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ChangePasswordFormTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_i_can_change_my_password(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $newPassword = self::generatedSecret();
        self::loginAsUserWithPassword($client, $password);
        $formIri = self::createForm(ChangePasswordType::class);

        self::submit($client, $formIri, ['oldPassword' => $password, 'plainPassword' => ['first' => $newPassword, 'second' => $newPassword]]);

        self::assertResponseStatusCodeSame(201);
        self::assertEmailOfTypeSent('change_password_notification');
        self::assertPasswordIs($newPassword, 'user@example.com');
        self::assertJsonMatchesSchemaFile('user.schema.json');
    }

    public function test_i_cannot_change_my_password_if_my_old_password_is_incorrect(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $newPassword = self::generatedSecret();
        self::loginAsUserWithPassword($client, $password);
        $formIri = self::createForm(ChangePasswordType::class);

        self::submit($client, $formIri, ['oldPassword' => self::generatedSecret(), 'plainPassword' => ['first' => $newPassword, 'second' => $newPassword]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNoEmailSent();
        self::assertJsonMatchesSchemaFile('form.schema.json');
        self::assertJsonNodeSame('formView.children[1].vars.errors[0]', 'You have not entered your current password correctly. Please try again.');
        self::assertPasswordIs($password, 'user@example.com');
    }

    public function test_i_cannot_change_my_password_if_my_passwords_do_not_match(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        self::loginAsUserWithPassword($client, $password);
        $formIri = self::createForm(ChangePasswordType::class);

        self::submit($client, $formIri, ['oldPassword' => $password, 'plainPassword' => ['first' => self::generatedSecret(), 'second' => self::generatedSecret()]]);

        self::assertResponseStatusCodeSame(422);
        self::assertNoEmailSent();
        self::assertJsonMatchesSchemaFile('form.schema.json');
        self::assertJsonNodeSame('formView.children[2].children[0].vars.errors[0]', 'The passwords you entered are not the same.');
        self::assertPasswordIs($password, 'user@example.com');
    }

    public function test_if_i_submit_the_username_field_it_is_ignored(): void
    {
        $client = static::createClient();
        $password = self::generatedSecret();
        $newPassword = self::generatedSecret();
        self::loginAsUserWithPassword($client, $password);
        $formIri = self::createForm(ChangePasswordType::class);
        self::createUserWithRole('another_user', $password, 'ROLE_USER');

        self::submit($client, $formIri, ['username' => 'another_user', 'oldPassword' => $password, 'plainPassword' => ['first' => $newPassword, 'second' => $newPassword]]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertJsonNodeSame('username', 'new_user');
        self::assertJsonNodeSame('emailAddress', 'user@example.com');
        self::assertPasswordIs($newPassword, 'user@example.com');
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function submit(Client $client, string $formIri, array $data): ResponseInterface
    {
        return self::requestJsonLd($client, 'POST', $formIri . '/submit', ['change_password' => $data], ['referer' => 'http://www.website.com']);
    }
}
