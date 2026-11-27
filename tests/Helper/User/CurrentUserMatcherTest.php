<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Silverback\ApiComponentsBundle\Helper\User\CurrentUserMatcher;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

final class CurrentUserMatcherTest extends TestCase
{
    public function test_the_token_user_matches_by_identity_even_as_another_instance(): void
    {
        $id = Uuid::uuid4();

        self::assertTrue(self::matcherFor(self::user($id, ['ROLE_USER']))->isCurrentUser(self::user($id, ['ROLE_USER'])));
    }

    public function test_a_token_user_with_every_admin_role_does_not_match_another_user(): void
    {
        $admin = self::user(Uuid::uuid4(), ['ROLE_SUPER_ADMIN', 'ROLE_USER_ADMIN', 'ROLE_ADMIN']);

        self::assertFalse(self::matcherFor($admin)->isCurrentUser(self::user(Uuid::uuid4(), ['ROLE_USER'])));
    }

    public function test_a_user_sharing_the_token_users_username_and_roles_but_not_its_id_does_not_match(): void
    {
        $current = self::user(Uuid::uuid4(), ['ROLE_USER']);

        self::assertFalse(self::matcherFor($current)->isCurrentUser(self::user(Uuid::uuid4(), ['ROLE_USER'])));
    }

    public function test_no_token_matches_nobody(): void
    {
        self::assertFalse((new CurrentUserMatcher(new TokenStorage()))->isCurrentUser(self::user(Uuid::uuid4(), ['ROLE_USER'])));
    }

    public function test_a_token_user_that_is_not_a_bundle_user_matches_nobody(): void
    {
        self::assertFalse(self::matcherFor(new InMemoryUser('new_user', null))->isCurrentUser(self::user(Uuid::uuid4(), ['ROLE_USER'])));
    }

    public function test_an_unsaved_user_never_matches(): void
    {
        self::assertFalse(self::matcherFor(new User('new_user'))->isCurrentUser(new User('new_user')));
    }

    private static function matcherFor(UserInterface $current): CurrentUserMatcher
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($current, 'main', $current->getRoles()));

        return new CurrentUserMatcher($tokenStorage);
    }

    /**
     * @param list<string> $roles
     */
    private static function user(UuidInterface $id, array $roles): User
    {
        $user = new User('new_user', 'user@example.com', roles: $roles);
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
