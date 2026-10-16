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
use Silverback\ApiComponentsBundle\AttributeReader\ExplicitAllowOnlyAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionIriResolver;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
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
        private readonly ExplicitAllowOnlyAttributeReader $explicitAllowOnlyReader,
        private readonly ListedComponentPositions $listedComponentPositions,
        private readonly ComponentPositionIriResolver $positionIriResolver,
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

        $this->validateMaxComponents($componentPosition, $constraint, $collection);
        $this->validateAnchors($componentPosition, $constraint, $collection);

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

    private function validateMaxComponents(ComponentPosition $componentPosition, ComponentPositionConstraint $constraint, ComponentGroup $collection): void
    {
        $limit = $collection->maxComponents;
        if (null === $limit || $collection->componentPositions->contains($componentPosition)) {
            return;
        }

        if (\count($this->listedComponentPositions->of($collection)) < $limit) {
            return;
        }

        $this->context->buildViolation($constraint->fullMessage)
            ->atPath('componentGroup')
            ->setParameter('{{ reference }}', (string) $collection->reference)
            ->setParameter('{{ limit }}', (string) $limit)
            ->setPlural($limit)
            ->addViolation();
    }

    private function validateAnchors(ComponentPosition $componentPosition, ComponentPositionConstraint $constraint, ComponentGroup $collection): void
    {
        if (null !== $componentPosition->after && null !== $componentPosition->before) {
            $this->context->buildViolation($constraint->bothAnchorsMessage)
                ->atPath('before')
                ->addViolation();

            return;
        }

        foreach (['after' => $componentPosition->after, 'before' => $componentPosition->before] as $path => $iri) {
            if (null === $iri) {
                continue;
            }

            if ($componentPosition->wasSortValueSent()) {
                $this->context->buildViolation($constraint->anchorWithSortValueMessage)
                    ->atPath($path)
                    ->addViolation();

                continue;
            }

            $anchor = $this->positionIriResolver->resolve($iri);
            if ($anchor === $componentPosition) {
                $this->context->buildViolation($constraint->selfAnchorMessage)
                    ->atPath($path)
                    ->addViolation();

                continue;
            }

            if (!$anchor || $anchor->componentGroup !== $collection) {
                $this->context->buildViolation($constraint->anchorNotInGroupMessage)
                    ->atPath($path)
                    ->setParameter('{{ iri }}', $iri)
                    ->setParameter('{{ reference }}', (string) $collection->reference)
                    ->addViolation();
            }
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

        if ($this->explicitAllowOnlyReader->isConfigured($component)) {
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

        $componentClass = $propertyMetadata->getComponentClass();
        $iri = $this->iriConverter->getIriFromResource($componentClass, UrlGeneratorInterface::ABS_PATH, (new GetCollection())->withClass($componentClass));

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

        if ($this->explicitAllowOnlyReader->isConfigured($componentClass)) {
            $this->context->buildViolation($constraint->restrictedMessage)
                ->setParameter('{{ iri }}', $iri)
                ->setParameter('{{ reference }}', $collection->reference)
                ->addViolation();
        }
    }
}
