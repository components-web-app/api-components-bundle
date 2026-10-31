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
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NewEmailAddressRequiresPasswordValidator extends ConstraintValidator
{
    public function __construct(private readonly ManagerRegistry $registry)
    {
    }

    public function validate(mixed $user, Constraint $constraint): void
    {
        if (!$constraint instanceof NewEmailAddressRequiresPassword) {
            throw new UnexpectedTypeException($constraint, NewEmailAddressRequiresPassword::class);
        }

        if (!$user instanceof AbstractUser) {
            throw new UnexpectedTypeException($user, AbstractUser::class);
        }

        $newEmailAddress = $user->getNewEmailAddress();
        if (null === $newEmailAddress || '' === $newEmailAddress || $newEmailAddress === $this->storedNewEmailAddress($user)) {
            return;
        }

        $this->context->getValidator()
            ->inContext($this->context)
            ->atPath('oldPassword')
            ->validate($user->getOldPassword(), new UserPassword(message: $constraint->message, groups: $constraint->groups));
    }

    private function storedNewEmailAddress(AbstractUser $user): ?string
    {
        $manager = $this->registry->getManagerForClass($user::class);
        if (!$manager instanceof EntityManagerInterface) {
            return null;
        }

        $original = $manager->getUnitOfWork()->getOriginalEntityData($user);

        return \array_key_exists('newEmailAddress', $original) && \is_string($original['newEmailAddress']) ? $original['newEmailAddress'] : null;
    }
}
