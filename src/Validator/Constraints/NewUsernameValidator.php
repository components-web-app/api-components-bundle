<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NewUsernameValidator extends ConstraintValidator
{
    private UserRepository $userRepository;

    public function __construct(
        UserRepository $userRepository
    ) {
        $this->userRepository = $userRepository;
    }

    public function validate($user, Constraint $constraint): void
    {
        if (!$user instanceof AbstractUser) {
            throw new UnexpectedTypeException($user, AbstractUser::class);
        }

        if (!$user->getUsername() || !$user->getNewEmailAddress()) {
            return;
        }
        if ($user->getNewEmailAddress() === $user->getUsername()) {
            $this->context->buildViolation($constraint->differentMessage)
                ->addViolation();

            return;
        }

        if ($this->userRepository->findOneBy(['username' => $user->getNewEmailAddress()])) {
            $this->context->buildViolation($constraint->uniqueMessage)
                ->addViolation();

            return;
        }
    }
}
