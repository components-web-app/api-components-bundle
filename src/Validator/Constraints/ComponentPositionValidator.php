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

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionValidator extends ConstraintValidator
{
    /**
     * @param ComponentPosition           $componentPosition
     * @param ComponentPositionConstraint $constraint
     */
    public function validate($componentPosition, Constraint $constraint): void
    {
        $collection = $componentPosition->componentCollection;
        if (($allowed = $collection->allowedComponents) && !$allowed->contains($className = \get_class($componentPosition->component))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ component }}', $className)
                ->setParameter('{{ reference }}', $collection->reference)
                ->addViolation();
        }
    }
}
