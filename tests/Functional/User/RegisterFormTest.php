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
use Silverback\ApiComponentsBundle\Form\Type\User\UserRegisterType;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;

final class RegisterFormTest extends FunctionalTestCase
{
    use UserTrait;

    private const array REFERER = ['referer' => 'http://www.website.com'];

    public function test_submit_a_user_registration_form(): void
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
        ], self::REFERER);

        self::assertResponseStatusCodeSame(201);
        self::lastJson();
        self::assertStringContainsString('application/ld+json', (string) self::getClient()->getResponse()->headers->get('content-type'));
        self::assertJsonContains([
            '@context' => '/contexts/User',
            '@type' => 'User',
            'username' => 'new_user',
            'emailAddress' => 'user@example.com',
            '_metadata' => ['persisted' => true],
        ]);
        self::assertJsonMatchesSchemaFile('user.schema.json');
        self::assertEmailOfTypeSent('user_welcome');
    }

    #[DataProvider('duplicateRegistrations')]
    public function test_submit_a_duplicate_user_registration_form(string $username, string $emailAddress, string $errorPath, string $errorMessage): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserRegisterType::class);
        self::createUserWithRole('new_user', self::generatedSecret(), 'ROLE_USER', 'user@example.com');
        $shortPassword = substr(self::generatedSecret(), 0, 2);

        self::requestJsonLd($client, 'POST', $formIri . '/submit', [
            'user_register' => [
                'username' => $username,
                'emailAddress' => $emailAddress,
                'plainPassword' => ['first' => $shortPassword, 'second' => $shortPassword],
            ],
        ], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::lastJson();
        self::assertJsonNodeSame($errorPath, $errorMessage);
        self::assertJsonNodeSame('formView.children[2].children[0].vars.errors[0]', 'Your password must be more than 6 characters long.');
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    public static function duplicateRegistrations(): iterable
    {
        yield 'a duplicate username' => ['new_user', 'different@email.com', 'formView.children[0].vars.errors[0]', 'Sorry, that user already exists in the database.'];
        yield 'a duplicate email address' => ['different_user', 'user@example.com', 'formView.children[1].vars.errors[0]', 'Sorry, that email address already exists in the database.'];
    }

    public function test_submit_an_invalid_user_registration_form(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserRegisterType::class);
        self::createUserWithRole('user', self::generatedSecret(), 'ROLE_USER');

        self::requestJsonLd($client, 'POST', $formIri . '/submit', ['user_register' => ['username' => '']], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::lastJson();
        self::assertJsonNodeSame('formView.children[0].vars.errors[0]', 'Please enter a username.');
        self::assertJsonNodeSame('formView.children[1].vars.errors[0]', 'Please enter your email address.');
        self::assertJsonNodeSame('formView.children[2].children[0].vars.errors[0]', 'Please enter your desired password.');
        self::assertJsonMatchesSchemaFile('form.schema.json');
    }

    public function test_a_valid_patch_to_the_register_form_only_validates_and_never_registers_the_user(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserRegisterType::class);
        $password = self::generatedSecret();

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', [
            'user_register' => [
                'username' => 'typing_user',
                'emailAddress' => 'typing@example.com',
                'plainPassword' => ['first' => $password, 'second' => $password],
            ],
        ], self::REFERER);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(0, self::countUsersWithUsername('typing_user'));
        self::assertNoEmailSent();
        self::assertJsonNodeSame('@type', 'Form');
        self::assertJsonNodeSame('formView.vars.valid', true);
    }

    public function test_an_invalid_patch_to_the_register_form_still_returns_the_validation_errors(): void
    {
        $client = static::createClient();
        $formIri = self::createForm(UserRegisterType::class);

        self::requestJsonLd($client, 'PATCH', $formIri . '/submit', ['user_register' => ['username' => '', 'emailAddress' => 'not-an-email']], self::REFERER);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('formView.children[0].vars.errors[0]', 'Please enter a username.');
        self::assertSame(0, self::countUsersWithUsername(''));
    }
}
