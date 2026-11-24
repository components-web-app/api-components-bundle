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

final class UserAdminTest extends FunctionalTestCase
{
    use UserTrait;

    public function test_a_user_admin_lists_users(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, 'GET', '/users');

        self::assertResponseStatusCodeSame(200);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function callersWhoAreNotUserAdmins(): iterable
    {
        yield 'a cms admin' => [['ROLE_CMS_ADMIN']];
        yield 'an admin' => [['ROLE_ADMIN']];
        yield 'a plain user' => [['ROLE_USER']];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('callersWhoAreNotUserAdmins')]
    public function test_a_caller_without_the_user_admin_role_cannot_list_users(array $roles): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, $roles);

        self::requestJsonLd($client, 'GET', '/users');

        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_cms_admin_cannot_read_another_user(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_CMS_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');

        self::requestJsonLd($client, 'GET', self::iriOf($other));

        self::assertResponseStatusCodeSame(403);
    }

    public function test_a_user_admin_reads_the_user_management_fields_of_another_user(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_ADMIN', 'other@example.com');

        self::requestJsonLd($client, 'GET', self::iriOf($other));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonMatchesSchemaFile('user_super_admin.schema.json');
    }

    public function test_a_user_admin_creates_a_user_with_a_role_they_reach(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, 'POST', '/users', ['username' => 'created_user', 'emailAddress' => 'created@example.com', 'plainPassword' => self::generatedSecret(), 'roles' => ['ROLE_USER_ADMIN']]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(['ROLE_USER_ADMIN'], self::findUserByUsername('created_user')?->getRoles());
    }

    public function test_a_user_admin_cannot_create_a_user_with_a_role_they_do_not_reach(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, 'POST', '/users', ['username' => 'created_user', 'emailAddress' => 'created@example.com', 'plainPassword' => self::generatedSecret(), 'roles' => ['ROLE_ADMIN']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'roles');
        self::assertSame(0, self::countUsersWithUsername('created_user'));
    }

    public function test_a_user_admin_edits_and_deletes_a_plain_user(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');
        $iri = self::iriOf($other);

        self::requestJsonLd($client, 'PATCH', $iri, ['enabled' => false]);
        self::assertResponseStatusCodeSame(200);
        self::assertFalse(self::findUserByUsername('other_user')?->isEnabled());

        self::requestJsonLd($client, 'DELETE', $iri);
        self::assertResponseStatusCodeSame(204);
        self::assertNull(self::findUserByUsername('other_user'));
    }

    public function test_a_user_admin_grants_a_plain_user_a_role_they_reach(): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');

        self::requestJsonLd($client, 'PATCH', self::iriOf($other), ['roles' => ['ROLE_USER', 'ROLE_USER_ADMIN']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ROLE_USER', 'ROLE_USER_ADMIN'], self::findUserByUsername('other_user')?->getRoles());
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function rolesAUserAdminDoesNotReach(): iterable
    {
        yield 'the cms admin role' => [['ROLE_USER', 'ROLE_CMS_ADMIN']];
        yield 'the admin role' => [['ROLE_ADMIN']];
        yield 'the super admin role' => [['ROLE_SUPER_ADMIN']];
        yield 'a role outside the hierarchy' => [['ROLE_USER', 'ROLE_SHOP_ACCOUNTS']];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('rolesAUserAdminDoesNotReach')]
    public function test_a_user_admin_cannot_grant_a_role_they_do_not_reach(array $roles): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');

        self::requestJsonLd($client, 'PATCH', self::iriOf($other), ['roles' => $roles]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'roles');
        self::assertSame(['ROLE_USER'], self::findUserByUsername('other_user')?->getRoles());
    }

    public function test_a_user_admin_cannot_grant_themselves_a_role_they_do_not_reach(): void
    {
        $client = static::createClient();
        $self = self::loginWithRoles($client, ['ROLE_USER_ADMIN']);

        self::requestJsonLd($client, 'PATCH', self::iriOf($self), ['roles' => ['ROLE_USER_ADMIN', 'ROLE_SUPER_ADMIN']]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'roles');
        self::assertSame(['ROLE_USER_ADMIN'], self::findUserByUsername('new_user')?->getRoles());
    }

    public function test_a_plain_user_cannot_grant_themselves_a_role(): void
    {
        $client = static::createClient();
        $self = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($self), ['roles' => ['ROLE_SUPER_ADMIN']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ROLE_USER'], self::findUserByUsername('new_user')?->getRoles());
    }

    /**
     * @return iterable<string, array{string, string, array<string, mixed>|null}>
     */
    public static function writesToAUserTheUserAdminDoesNotOutrank(): iterable
    {
        foreach (['a cms admin' => 'ROLE_CMS_ADMIN', 'an admin' => 'ROLE_ADMIN', 'a super admin' => 'ROLE_SUPER_ADMIN'] as $name => $role) {
            yield 'an edit of ' . $name => [$role, 'PATCH', ['enabled' => false]];
            yield 'a role change of ' . $name => [$role, 'PATCH', ['roles' => ['ROLE_USER']]];
            yield 'a replace of ' . $name => [$role, 'PUT', ['username' => 'renamed']];
            yield 'a delete of ' . $name => [$role, 'DELETE', null];
        }
    }

    /**
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('writesToAUserTheUserAdminDoesNotOutrank')]
    public function test_a_user_admin_cannot_write_a_user_who_reaches_a_role_they_do_not(string $role, string $method, ?array $body): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, ['ROLE_USER_ADMIN']);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), $role, 'other@example.com');

        self::requestJsonLd($client, $method, self::iriOf($other), $body);

        self::assertResponseStatusCodeSame(403);
        $stored = self::findUserByUsername('other_user');
        self::assertNotNull($stored);
        self::assertSame([$role], $stored->getRoles());
        self::assertTrue($stored->isEnabled());
    }

    public function test_a_super_admin_grants_the_admin_role(): void
    {
        $client = static::createClient();
        self::loginAsSuperAdmin($client);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');

        self::requestJsonLd($client, 'PATCH', self::iriOf($other), ['roles' => ['ROLE_ADMIN']]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame(['ROLE_ADMIN'], self::findUserByUsername('other_user')?->getRoles());
    }
}
