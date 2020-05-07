<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Validator\Constraints;

use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class NewEmailAddressValidator extends ConstraintValidator
{
    use ClassMetadataTrait;

    public function __construct(ManagerRegistry $registry)
    {
        $this->initRegistry($registry);
    }

    /**
     * @param NewEmailAddress $constraint
     */
    public function validate($user, Constraint $constraint): void
    {
        if (!$manager = $this->registry->getManagerForClass($userClass = \get_class($user))) {
            throw new UnexpectedTypeException($user, AbstractUser::class);
        }

        $classMetadata = $this->getClassMetadata($user);

        if (!$newEmailAddress = $classMetadata->getFieldValue($user, $constraint->newEmailAddressField)) {
            return;
        }

        if ($newEmailAddress === $classMetadata->getFieldValue($user, $constraint->emailAddressField)) {
            $this->context->buildViolation($constraint->message)
                ->atPath($constraint->newEmailAddressField)
                ->addViolation();

            return;
        }

        $userRepository = $manager->getRepository($userClass);

        if ($userRepository->findOneBy([$constraint->emailAddressField => $newEmailAddress])) {
            $this->context->buildViolation($constraint->uniqueMessage)
                ->atPath($constraint->newEmailAddressField)
                ->addViolation();

            return;
        }
    }
}
