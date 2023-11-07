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

use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use ApiPlatform\Metadata\GetCollection;
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
        $collection = $componentPosition->componentGroup;
        if (!$collection) {
            return;
        }
        $component = $componentPosition->component;
        if (!$component) {
            return;
        }

        $resourceClass = $component::class;
        $iri = $this->iriConverter->getIriFromResource($resourceClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($resourceClass));

        if ($allowedComponents = $collection->allowedComponents) {
            if (!\in_array($iri, $allowedComponents, true)) {
                $this->context->buildViolation($constraint->message)
                    ->setParameter('{{ iri }}', $iri)
                    ->setParameter('{{ reference }}', $collection->reference)
                    ->setParameter('{{ allowed }}', implode(',', $allowedComponents))
                    ->addViolation();
            }

            return;
        }

        if ($component->isPositionRestricted()) {
            $this->context->buildViolation($constraint->restrictedMessage)
                ->setParameter('{{ iri }}', $iri)
                ->setParameter('{{ reference }}', $collection->reference)
                ->addViolation();
        }
    }
}
