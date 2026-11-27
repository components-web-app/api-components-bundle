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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

final class AdminPreferencesTest extends FunctionalTestCase
{
    use UserTrait;

    private const array PREFERENCES = ['topBar' => ['layouts', 'pages', 'ext:shop']];

    public function test_me_carries_admin_preferences_as_null_for_a_new_user(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);

        $json = self::requestJsonLd($client, 'GET', '/me')->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('adminPreferences', $json);
        self::assertNull($json['adminPreferences']);
    }

    public function test_a_user_stores_their_own_admin_preferences_without_their_password(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['adminPreferences' => self::PREFERENCES]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('adminPreferences', self::PREFERENCES);
        self::assertSame(self::PREFERENCES, self::storedPreferences($user));

        self::requestJsonLd($client, 'GET', '/me');
        self::assertJsonNodeSame('adminPreferences', self::PREFERENCES);
    }

    public function test_a_user_clears_their_own_admin_preferences_with_null(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::givePreferences($user);

        $json = self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['adminPreferences' => null])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('adminPreferences', $json);
        self::assertNull($json['adminPreferences']);
        self::assertNull(self::storedPreferences($user));
    }

    public function test_a_user_reads_their_own_admin_preferences_by_iri(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);
        self::givePreferences($user);

        self::requestJsonLd($client, 'GET', self::iriOf($user));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('adminPreferences', self::PREFERENCES);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function managersOfOtherUsers(): iterable
    {
        yield 'a user admin' => [['ROLE_USER_ADMIN']];
        yield 'a super admin' => [['ROLE_SUPER_ADMIN']];
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('managersOfOtherUsers')]
    public function test_a_manager_of_other_users_never_reads_another_users_admin_preferences(array $roles): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, $roles);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');
        self::givePreferences($other);

        self::requestJsonLd($client, 'GET', self::iriOf($other));

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('adminPreferences');
    }

    /**
     * @param list<string> $roles
     */
    #[DataProvider('managersOfOtherUsers')]
    public function test_a_manager_of_other_users_never_writes_another_users_admin_preferences(array $roles): void
    {
        $client = static::createClient();
        self::loginWithRoles($client, $roles);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');
        self::givePreferences($other);

        self::requestJsonLd($client, 'PATCH', self::iriOf($other), ['adminPreferences' => ['topBar' => ['hijacked']]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('adminPreferences');
        self::assertSame(self::PREFERENCES, self::storedPreferences($other));
    }

    public function test_the_users_collection_never_carries_admin_preferences_not_even_the_callers_own(): void
    {
        $client = static::createClient();
        $admin = self::loginAsSuperAdmin($client);
        self::givePreferences($admin);
        $other = self::createUserWithRole('other_user', self::generatedSecret(), 'ROLE_USER', 'other@example.com');
        self::givePreferences($other);

        $members = self::requestJsonLd($client, 'GET', '/users')->toArray()['member'];

        self::assertResponseStatusCodeSame(200);
        self::assertContains(self::iriOf($admin), array_column($members, '@id'));
        self::assertContains(self::iriOf($other), array_column($members, '@id'));
        foreach ($members as $member) {
            self::assertArrayNotHasKey('adminPreferences', $member);
        }
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatAreNotAnObject(): iterable
    {
        yield 'a string' => ['layouts'];
        yield 'a number' => [42];
        yield 'a list' => [['layouts', 'pages']];
    }

    #[DataProvider('valuesThatAreNotAnObject')]
    public function test_admin_preferences_that_are_not_an_object_are_a_violation(mixed $value): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['adminPreferences' => $value]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'adminPreferences');
        self::assertNull(self::storedPreferences($user));
    }

    public function test_admin_preferences_over_4096_bytes_are_a_violation(): void
    {
        $client = static::createClient();
        $user = self::loginAsUser($client);

        self::requestJsonLd($client, 'PATCH', self::iriOf($user), ['adminPreferences' => ['topBar' => [str_repeat('a', 4096)]]]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonNodeSame('violations[0].propertyPath', 'adminPreferences');
        self::assertNull(self::storedPreferences($user));
    }

    private static function givePreferences(User $user): void
    {
        static::changeUser($user, static fn (User $stored) => $stored->setAdminPreferences(self::PREFERENCES));
    }

    private static function storedPreferences(User $user): mixed
    {
        $manager = static::getEntityManager();
        $manager->clear();

        return $manager->find(User::class, $user->getId())?->getAdminPreferences();
    }
}
