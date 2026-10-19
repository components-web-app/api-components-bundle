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

use ApiPlatform\Metadata\Exception\ExceptionInterface;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

final class ComponentMoveRequestResolver
{
    public const string FROM_SHAPE_MESSAGE = 'Send either componentPosition, or pageData and property.';
    public const string TO_SHAPE_MESSAGE = 'Send either componentGroup, with after or before, or pageData and property.';
    public const string NOT_A_STRING_MESSAGE = 'This value should be a string.';
    public const string NOT_FOUND_MESSAGE = 'The IRI `{{ iri }}` is not a {{ type }}.';

    private const array FROM_SHAPES = [['componentPosition'], ['pageData', 'property']];
    private const array TO_SHAPES = [['componentGroup'], ['componentGroup', 'after'], ['componentGroup', 'before'], ['componentGroup', 'after', 'before'], ['pageData', 'property']];

    public function __construct(private readonly IriConverterInterface $iriConverter)
    {
    }

    /**
     * @return array{ComponentMoveEndpoint, ComponentMoveEndpoint}
     */
    public function resolve(mixed $from, mixed $to): array
    {
        $violations = new ConstraintViolationList();
        $source = $this->resolveEndpoint($violations, 'from', $from, self::FROM_SHAPES, self::FROM_SHAPE_MESSAGE);
        $target = $this->resolveEndpoint($violations, 'to', $to, self::TO_SHAPES, self::TO_SHAPE_MESSAGE);

        if (null === $source || null === $target || \count($violations)) {
            throw new ValidationException($violations);
        }

        return [$source, $target];
    }

    /**
     * @param list<list<string>> $shapes
     */
    private function resolveEndpoint(ConstraintViolationList $violations, string $path, mixed $value, array $shapes, string $shapeMessage): ?ComponentMoveEndpoint
    {
        if (!\is_array($value) || !$this->hasShape($value, $shapes)) {
            $this->addViolation($violations, $shapeMessage, $path, $value);

            return null;
        }

        if (\array_key_exists('componentPosition', $value)) {
            $position = $this->resource($violations, $path . '.componentPosition', $value['componentPosition'], ComponentPosition::class, 'component position');

            return $position ? ComponentMoveEndpoint::position($path, $position) : null;
        }

        if (\array_key_exists('componentGroup', $value)) {
            $group = $this->resource($violations, $path . '.componentGroup', $value['componentGroup'], ComponentGroup::class, 'component group');
            $after = $this->string($violations, $path . '.after', $value['after'] ?? null);
            $before = $this->string($violations, $path . '.before', $value['before'] ?? null);

            return $group ? ComponentMoveEndpoint::group($path, $group, $after, $before) : null;
        }

        $pageData = $this->resource($violations, $path . '.pageData', $value['pageData'], AbstractPageData::class, 'page data resource');
        $property = $this->string($violations, $path . '.property', $value['property']);

        return $pageData && null !== $property ? ComponentMoveEndpoint::slot($path, $pageData, $property) : null;
    }

    /**
     * @param array<mixed>       $value
     * @param list<list<string>> $shapes
     */
    private function hasShape(array $value, array $shapes): bool
    {
        $keys = array_keys($value);
        sort($keys);
        foreach ($shapes as $shape) {
            sort($shape);
            if ($keys === $shape) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    private function resource(ConstraintViolationList $violations, string $path, mixed $iri, string $class, string $type): ?object
    {
        if (!\is_string($iri)) {
            $this->addViolation($violations, self::NOT_A_STRING_MESSAGE, $path, $iri);

            return null;
        }

        try {
            $resource = $this->iriConverter->getResourceFromIri($iri);
        } catch (ExceptionInterface) {
            $resource = null;
        }

        if ($resource instanceof $class) {
            return $resource;
        }

        $this->addViolation($violations, self::NOT_FOUND_MESSAGE, $path, $iri, ['{{ iri }}' => $iri, '{{ type }}' => $type]);

        return null;
    }

    private function string(ConstraintViolationList $violations, string $path, mixed $value): ?string
    {
        if (null === $value || \is_string($value)) {
            return $value;
        }

        $this->addViolation($violations, self::NOT_A_STRING_MESSAGE, $path, $value);

        return null;
    }

    /**
     * @param array<string, string> $parameters
     */
    private function addViolation(ConstraintViolationList $violations, string $template, string $path, mixed $invalidValue, array $parameters = []): void
    {
        $violations->add(new ConstraintViolation(strtr($template, $parameters), $template, $parameters, null, $path, $invalidValue));
    }
}
