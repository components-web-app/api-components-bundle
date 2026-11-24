<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Security\Voter;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * @extends Voter<string, AbstractUser>
 */
final class UserManagementVoter extends Voter
{
    public const string MANAGE_USER = 'manage_user';

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE_USER === $attribute && $subject instanceof AbstractUser;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (null === $token->getUser()) {
            return false;
        }

        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());

        return [] === array_diff($this->roleHierarchy->getReachableRoleNames($subject->getRoles()), $reachable);
    }
}
