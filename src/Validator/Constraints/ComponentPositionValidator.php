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

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Metadata\Provider\PageDataMetadataProvider;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionValidator extends ConstraintValidator
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly PageDataMetadataProvider $pageDataMetadataProvider,
    ) {
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
        if ($component) {
            $this->validateDirectComponent($componentPosition, $constraint, $component, $collection);

            return;
        }

        $pageDataClass = $componentPosition->pageDataClass;
        $pageDataProperty = $componentPosition->pageDataProperty;
        if ($pageDataClass && $pageDataProperty) {
            $this->validateDynamicPosition($componentPosition, $constraint, $pageDataClass, $pageDataProperty, $collection);
        }
    }

    private function validateDirectComponent(ComponentPosition $componentPosition, ComponentPositionConstraint $constraint, object $component, object $collection): void
    {
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

    private function validateDynamicPosition(ComponentPosition $componentPosition, ComponentPositionConstraint $constraint, string $pageDataClass, string $pageDataProperty, object $collection): void
    {
        $pageDataMetadata = null;
        foreach ($this->pageDataMetadataProvider->createAll() as $metadata) {
            if ($metadata->getResourceClass() === $pageDataClass) {
                $pageDataMetadata = $metadata;
                break;
            }
        }

        if (!$pageDataMetadata) {
            $this->context->buildViolation($constraint->invalidPageDataClassMessage)
                ->setParameter('{{ class }}', $pageDataClass)
                ->addViolation();

            return;
        }

        $propertyMetadata = null;
        foreach ($pageDataMetadata->getProperties() as $property) {
            if ($property->getProperty() === $pageDataProperty) {
                $propertyMetadata = $property;
                break;
            }
        }

        if (!$propertyMetadata) {
            $this->context->buildViolation($constraint->invalidPageDataPropertyMessage)
                ->setParameter('{{ property }}', $pageDataProperty)
                ->setParameter('{{ class }}', $pageDataClass)
                ->addViolation();

            return;
        }

        if (!$allowedComponents = $collection->allowedComponents) {
            return;
        }

        $componentClass = $propertyMetadata->getComponentClass();
        $iri = $this->iriConverter->getIriFromResource($componentClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($componentClass));

        if (!\in_array($iri, $allowedComponents, true)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ iri }}', $iri)
                ->setParameter('{{ reference }}', $collection->reference)
                ->setParameter('{{ allowed }}', implode(',', $allowedComponents))
                ->addViolation();
        }
    }
}
