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

use ApiPlatform\Core\Api\IriConverterInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionValidator extends ConstraintValidator
{
    private IriConverterInterface $iriConverter;

    public function __construct(IriConverterInterface $iriConverter)
    {
        $this->iriConverter = $iriConverter;
    }

    /**
     * @param ComponentPosition           $componentPosition
     * @param ComponentPositionConstraint $constraint
     */
    public function validate($componentPosition, Constraint $constraint): void
    {
        $collection = $componentPosition->componentCollection;
        if (!$collection) {
            return;
        }

        $iri = $this->iriConverter->getIriFromResourceClass(\get_class($componentPosition->component));

        if ($allowedComponents = $collection->allowedComponents) {
            if (!$allowedComponents->contains($iri)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ iri }}', $iri)
                    ->setParameter('{{ reference }}', $collection->reference)
                    ->setParameter('{{ allowed }}', implode(',', $allowedComponents->toArray()))
                    ->addViolation();
            }

            return;
        }

        if ($componentPosition->component->isPositionRestricted()) {
            $this->context->buildViolation($constraint->restrictedMessage)
                ->setParameter('{{ iri }}', $iri)
                ->setParameter('{{ reference }}', $collection->reference)
                ->addViolation();
        }
    }
}
