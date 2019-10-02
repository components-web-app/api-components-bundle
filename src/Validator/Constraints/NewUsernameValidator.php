<?php

namespace Silverback\ApiComponentBundle\Validator\Constraints;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class NewUsernameValidator extends ConstraintValidator
{
    private $userRepository;
    public function __construct(
        UserRepository $userRepository
    ) {
        $this->userRepository = $userRepository;
    }

    public function validate($user, Constraint $constraint): void
    {
        if (!$user instanceof User) {
            throw new UnexpectedTypeException($user, User::class);
        }

        if (!$user->getUsername() || !$user->getNewUsername()) {
            return;
        }
        if ($user->getNewUsername() === $user->getUsername()) {
            $this->context->buildViolation($constraint->differentMessage)
                ->addViolation();
            return;
        }

        if ($this->userRepository->findOneBy(['username' => $user->getNewUsername()])) {
            $this->context->buildViolation($constraint->uniqueMessage)
                ->addViolation();
            return;
        }
    }
}
