<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AssignableRolesValidator extends ConstraintValidator
{
    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function validate(mixed $user, Constraint $constraint): void
    {
        if (!$constraint instanceof AssignableRoles) {
            throw new UnexpectedTypeException($constraint, AssignableRoles::class);
        }

        if (!$user instanceof AbstractUser) {
            throw new UnexpectedTypeException($user, AbstractUser::class);
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || null === $token->getUser()) {
            return;
        }

        $added = array_diff($user->getRoles(), $this->storedRoles($user));
        $refused = array_values(array_diff($added, $this->roleHierarchy->getReachableRoleNames($token->getRoleNames())));
        if ([] === $refused) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ roles }}', implode(', ', $refused))
            ->atPath('roles')
            ->addViolation();
    }

    /**
     * @return list<string>
     */
    private function storedRoles(AbstractUser $user): array
    {
        $manager = $this->registry->getManagerForClass($user::class);
        if (!$manager instanceof EntityManagerInterface) {
            return [];
        }

        $original = $manager->getUnitOfWork()->getOriginalEntityData($user);
        if (!\array_key_exists('roles', $original) || !\is_array($original['roles'])) {
            return [];
        }

        return array_values($original['roles']);
    }
}
