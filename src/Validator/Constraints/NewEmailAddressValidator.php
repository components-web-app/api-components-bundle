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

class NewEmailAddressValidator extends ConstraintValidator
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

        if (!$user->getNewEmailAddress()) {
            return;
        }

        if ($user->getNewEmailAddress() === $user->getEmailAddress()) {
            $this->context->buildViolation($constraint->differentMessage)
                ->addViolation();

            return;
        }

        if ($this->userRepository->findOneBy(['email_address' => $user->getNewEmailAddress()])) {
            $this->context->buildViolation($constraint->uniqueMessage)
                ->addViolation();

            return;
        }
    }
}
