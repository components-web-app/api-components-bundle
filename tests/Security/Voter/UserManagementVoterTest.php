<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Security\Voter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Security\AdminRoles;
use Silverback\ApiComponentsBundle\Security\Voter\UserManagementVoter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class UserManagementVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, list<string>, int}>
     */
    public static function votes(): iterable
    {
        yield 'a user admin managing a plain user' => [['ROLE_USER_ADMIN'], ['ROLE_USER'], VoterInterface::ACCESS_GRANTED];
        yield 'a user admin managing another user admin' => [['ROLE_USER_ADMIN'], ['ROLE_USER_ADMIN'], VoterInterface::ACCESS_GRANTED];
        yield 'a user admin managing a cms admin' => [['ROLE_USER_ADMIN'], ['ROLE_CMS_ADMIN'], VoterInterface::ACCESS_DENIED];
        yield 'a user admin managing an admin' => [['ROLE_USER_ADMIN'], ['ROLE_ADMIN'], VoterInterface::ACCESS_DENIED];
        yield 'a user admin managing a super admin' => [['ROLE_USER_ADMIN'], ['ROLE_SUPER_ADMIN'], VoterInterface::ACCESS_DENIED];
        yield 'a user admin managing a user with a role outside the hierarchy' => [['ROLE_USER_ADMIN'], ['ROLE_SHOP'], VoterInterface::ACCESS_DENIED];
        yield 'a super admin managing an admin' => [['ROLE_SUPER_ADMIN'], ['ROLE_ADMIN'], VoterInterface::ACCESS_GRANTED];
        yield 'a super admin managing a super admin' => [['ROLE_SUPER_ADMIN'], ['ROLE_SUPER_ADMIN'], VoterInterface::ACCESS_GRANTED];
        yield 'an admin and user admin managing an admin' => [['ROLE_ADMIN', 'ROLE_USER_ADMIN'], ['ROLE_ADMIN'], VoterInterface::ACCESS_GRANTED];
    }

    /**
     * @param list<string> $tokenRoles
     * @param list<string> $subjectRoles
     */
    #[DataProvider('votes')]
    public function test_a_user_is_managed_only_by_a_caller_who_reaches_every_role_they_reach(array $tokenRoles, array $subjectRoles, int $expected): void
    {
        $token = new UsernamePasswordToken(new User('caller', roles: $tokenRoles), 'main', $tokenRoles);

        self::assertSame($expected, self::voter()->vote($token, new User('subject', roles: $subjectRoles), [UserManagementVoter::MANAGE_USER]));
    }

    public function test_an_anonymous_caller_is_denied(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, self::voter()->vote(new NullToken(), new User('subject'), [UserManagementVoter::MANAGE_USER]));
    }

    public function test_it_abstains_on_another_attribute_or_subject(): void
    {
        $token = new UsernamePasswordToken(new User('caller', roles: ['ROLE_SUPER_ADMIN']), 'main', ['ROLE_SUPER_ADMIN']);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, self::voter()->vote($token, new User('subject'), ['edit']));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, self::voter()->vote($token, new \stdClass(), [UserManagementVoter::MANAGE_USER]));
    }

    private static function voter(): UserManagementVoter
    {
        return new UserManagementVoter(new RoleHierarchy(AdminRoles::HIERARCHY));
    }
}
