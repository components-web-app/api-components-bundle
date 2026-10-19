<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\ComponentMove;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionPlacer;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Utility\ClassMetadataTrait;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentPosition as ComponentPositionConstraint;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ComponentMover
{
    use ClassMetadataTrait;

    public const string DYNAMIC_POSITION_MESSAGE = 'A dynamic position cannot be moved: move the page data property instead.';
    public const string EMPTY_POSITION_MESSAGE = 'The position holds no component.';
    public const string OTHER_POSITION_COMPONENT_MESSAGE = 'The position does not hold this component.';
    public const string EMPTY_PROPERTY_MESSAGE = 'The property holds no component.';
    public const string OTHER_PROPERTY_COMPONENT_MESSAGE = 'The property does not hold this component.';
    public const string SAME_PROPERTY_MESSAGE = 'The component is already held by this property.';
    public const string OCCUPIED_PROPERTY_MESSAGE = 'The property already holds a component.';

    public function __construct(
        ManagerRegistry $registry,
        private readonly PageDataMetadataFactoryInterface $pageDataMetadataFactory,
        private readonly ComponentCycleDetector $cycleDetector,
        private readonly ComponentPositionPlacer $placer,
        private readonly ComponentMoveAccessChecker $accessChecker,
        private readonly ValidatorInterface $validator,
        private readonly TimestampedDataPersister $timestampedDataPersister,
    ) {
        $this->initRegistry($registry);
    }

    public function move(AbstractComponent $component, ComponentMoveEndpoint $from, ComponentMoveEndpoint $to): ?ComponentPosition
    {
        $this->denyUnlessGrantedWrites($from, $to);
        $held = $this->heldComponent($component, $from);
        $this->validateTargetSlot($held, $from, $to);

        $previous = [];
        foreach ([$from->pageData, $to->pageData, $from->componentPosition] as $entity) {
            if (null !== $entity) {
                $previous[spl_object_id($entity)] = clone $entity;
            }
        }

        $position = $this->writePosition($held, $from, $to);
        if ($to->pageData && $to->property) {
            $this->setSlot($to->pageData, $to->property, $held);
        }
        if ($from->pageData && $from->property) {
            $this->setSlot($from->pageData, $from->property, null);
        }

        $this->timestamp($from, $to, $position);
        $this->denyUnlessGrantedWritesPostDenormalize($from, $to, $position, $previous);
        $this->validateWrites($from, $to, $position);

        if ($position) {
            $this->placer->place($position);
        }
        $this->removeMovedOutPosition($held, $from, $to);

        $entityManager = $this->getEntityManager($held);
        if ($position && !$entityManager->contains($position)) {
            $entityManager->persist($position);
        }
        $entityManager->flush();

        return $position;
    }

    private function timestamp(ComponentMoveEndpoint $from, ComponentMoveEndpoint $to, ?ComponentPosition $position): void
    {
        if ($position) {
            $this->timestampedDataPersister->persistTimestampedFields($position, null === $from->componentPosition);
        }

        foreach ([$from->pageData, $to->pageData] as $pageData) {
            if ($pageData) {
                $this->timestampedDataPersister->persistTimestampedFields($pageData, false);
            }
        }
    }

    private function denyUnlessGrantedWrites(ComponentMoveEndpoint $from, ComponentMoveEndpoint $to): void
    {
        if ($from->componentPosition) {
            $this->accessChecker->denyUnlessGranted(ComponentPosition::class, $to->componentGroup ? HttpOperation::METHOD_PATCH : HttpOperation::METHOD_DELETE, $from->componentPosition);
        } elseif ($to->componentGroup) {
            $this->accessChecker->denyUnlessGranted(ComponentPosition::class, HttpOperation::METHOD_POST, null);
        }

        foreach ([$from->pageData, $to->pageData] as $pageData) {
            if ($pageData) {
                $this->accessChecker->denyUnlessGranted($this->getClassMetadata($pageData)->getName(), HttpOperation::METHOD_PATCH, $pageData);
            }
        }
    }

    /**
     * @param array<int, object> $previous
     */
    private function denyUnlessGrantedWritesPostDenormalize(ComponentMoveEndpoint $from, ComponentMoveEndpoint $to, ?ComponentPosition $position, array $previous): void
    {
        if ($position) {
            $method = $from->componentPosition ? HttpOperation::METHOD_PATCH : HttpOperation::METHOD_POST;
            $this->accessChecker->denyUnlessGrantedPostDenormalize(ComponentPosition::class, $method, $position, $previous[spl_object_id($position)] ?? null);
        }

        foreach ([$from->pageData, $to->pageData] as $pageData) {
            if ($pageData) {
                $this->accessChecker->denyUnlessGrantedPostDenormalize($this->getClassMetadata($pageData)->getName(), HttpOperation::METHOD_PATCH, $pageData, $previous[spl_object_id($pageData)] ?? null);
            }
        }
    }

    private function heldComponent(AbstractComponent $component, ComponentMoveEndpoint $from): AbstractComponent
    {
        $position = $from->componentPosition;
        if ($position) {
            $path = $from->propertyPath('componentPosition');
            if (null !== $position->pageDataProperty) {
                throw $this->validationException(self::DYNAMIC_POSITION_MESSAGE, $path);
            }

            return $this->heldBy($component, $position->component, $path, self::EMPTY_POSITION_MESSAGE, self::OTHER_POSITION_COMPONENT_MESSAGE);
        }

        $pageData = $from->pageData;
        $property = (string) $from->property;
        \assert($pageData instanceof AbstractPageData);
        $this->validateComponentProperty($component, $pageData, $property, $from);

        return $this->heldBy($component, $this->slot($pageData, $property), $from->propertyPath('property'), self::EMPTY_PROPERTY_MESSAGE, self::OTHER_PROPERTY_COMPONENT_MESSAGE);
    }

    private function heldBy(AbstractComponent $component, ?AbstractComponent $held, string $path, string $emptyMessage, string $otherMessage): AbstractComponent
    {
        if (null === $held) {
            throw $this->validationException($emptyMessage, $path);
        }

        if (!$this->cycleDetector->isSameComponent($component, $held)) {
            throw $this->validationException($otherMessage, $path);
        }

        return $held;
    }

    private function validateTargetSlot(AbstractComponent $held, ComponentMoveEndpoint $from, ComponentMoveEndpoint $to): void
    {
        $pageData = $to->pageData;
        if (!$pageData) {
            return;
        }

        $property = (string) $to->property;
        if ($pageData === $from->pageData && $property === $from->property) {
            throw $this->validationException(self::SAME_PROPERTY_MESSAGE, $to->path);
        }

        $this->validateComponentProperty($held, $pageData, $property, $to);
        if (null !== $this->slot($pageData, $property)) {
            throw $this->validationException(self::OCCUPIED_PROPERTY_MESSAGE, $to->propertyPath('property'));
        }
    }

    private function validateComponentProperty(AbstractComponent $component, AbstractPageData $pageData, string $property, ComponentMoveEndpoint $endpoint): void
    {
        $pageDataClass = $this->getClassMetadata($pageData)->getName();
        $properties = $this->pageDataMetadataFactory->create($pageDataClass)->findPropertiesByComponentClass($this->getClassMetadata($component)->getName());
        foreach ($properties as $propertyMetadata) {
            if ($propertyMetadata->getProperty() === $property) {
                return;
            }
        }

        $template = (new ComponentPositionConstraint())->invalidPageDataPropertyMessage;
        throw $this->validationException($template, $endpoint->propertyPath('property'), ['{{ property }}' => $property, '{{ class }}' => $pageDataClass]);
    }

    private function writePosition(AbstractComponent $held, ComponentMoveEndpoint $from, ComponentMoveEndpoint $to): ?ComponentPosition
    {
        $group = $to->componentGroup;
        if (!$group) {
            return null;
        }

        $position = $from->componentPosition ?? (new ComponentPosition())->setComponent($held);
        $position->componentGroup = $group;
        $position->after = $to->after;
        $position->before = $to->before;

        return $position;
    }

    private function validateWrites(ComponentMoveEndpoint $from, ComponentMoveEndpoint $to, ?ComponentPosition $position): void
    {
        $violations = new ConstraintViolationList();
        if ($position) {
            foreach ($this->validator->validate($position) as $violation) {
                $path = $violation->getPropertyPath();
                $violations->add($this->moved($violation, $to->propertyPath(\in_array($path, ['after', 'before'], true) ? $path : 'componentGroup')));
            }
        }

        foreach ([$from, $to] as $endpoint) {
            if ($endpoint->pageData) {
                $this->addPageDataViolations($violations, $endpoint, $this->validator->validate($endpoint->pageData));
            }
        }

        if (\count($violations)) {
            throw new ValidationException($violations);
        }
    }

    private function addPageDataViolations(ConstraintViolationList $violations, ComponentMoveEndpoint $endpoint, ConstraintViolationListInterface $pageDataViolations): void
    {
        foreach ($pageDataViolations as $violation) {
            $violations->add($this->moved($violation, $endpoint->propertyPath($violation->getPropertyPath() === $endpoint->property ? 'property' : 'pageData')));
        }
    }

    private function removeMovedOutPosition(AbstractComponent $held, ComponentMoveEndpoint $from, ComponentMoveEndpoint $to): void
    {
        $position = $from->componentPosition;
        if (!$position || $to->componentGroup) {
            return;
        }

        $position->componentGroup?->componentPositions->removeElement($position);
        $held->getComponentPositions()->removeElement($position);
        $this->getEntityManager($position)->remove($position);
    }

    private function slot(AbstractPageData $pageData, string $property): ?AbstractComponent
    {
        $entityManager = $this->getEntityManager($pageData);
        if ($entityManager->isUninitializedObject($pageData)) {
            $entityManager->initializeObject($pageData);
        }
        $held = $this->getClassMetadata($pageData)->getFieldValue($pageData, $property);

        return $held instanceof AbstractComponent ? $held : null;
    }

    private function setSlot(AbstractPageData $pageData, string $property, ?AbstractComponent $component): void
    {
        $this->getClassMetadata($pageData)->setFieldValue($pageData, $property, $component);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function validationException(string $template, string $path, array $parameters = []): ValidationException
    {
        return new ValidationException(new ConstraintViolationList([new ConstraintViolation(strtr($template, $parameters), $template, $parameters, null, $path, null)]));
    }

    private function moved(ConstraintViolationInterface $violation, string $path): ConstraintViolation
    {
        return new ConstraintViolation(
            $violation->getMessage(),
            $violation->getMessageTemplate(),
            $violation->getParameters(),
            $violation->getRoot(),
            $path,
            $violation->getInvalidValue(),
            $violation->getPlural(),
            $violation->getCode(),
            $violation instanceof ConstraintViolation ? $violation->getConstraint() : null,
        );
    }
}
