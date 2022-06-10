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

namespace Silverback\ApiComponentsBundle\Filter;

use ApiPlatform\Api\IriConverterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterInterface;
use ApiPlatform\Doctrine\Common\Filter\SearchFilterTrait;
use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryBuilderHelper;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Operation;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 *  ApiPlatform\Doctrine\Orm\Filter\SearchFilter but using 'or' instead of 'and'
 */
final class OrSearchFilter extends AbstractFilter implements SearchFilterInterface
{
    use SearchFilterTrait;

    public const DOCTRINE_INTEGER_TYPE = Types::INTEGER;

    /**
     * {@inheritdoc}
     */
    protected function getType(string $doctrineType): string
    {
        switch ($doctrineType) {
            case Types::ARRAY:
                return 'array';
            case Types::BIGINT:
            case Types::INTEGER:
            case Types::SMALLINT:
                return 'int';
            case Types::BOOLEAN:
                return 'bool';
            case Types::DATE_MUTABLE:
            case Types::TIME_MUTABLE:
            case Types::DATETIME_MUTABLE:
            case Types::DATETIMETZ_MUTABLE:
            case Types::DATE_IMMUTABLE:
            case Types::TIME_IMMUTABLE:
            case Types::DATETIME_IMMUTABLE:
            case Types::DATETIMETZ_IMMUTABLE:
                return \DateTimeInterface::class;
            case Types::FLOAT:
                return 'float';
        }

        return 'string';
    }

    protected function getIriConverter(): IriConverterInterface
    {
        return $this->iriConverter;
    }

    protected function getPropertyAccessor(): PropertyAccessorInterface
    {
        return $this->propertyAccessor;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, Operation $operation = null, array $context = []): void
    {
        if (
            null === $value ||
            !$this->isPropertyEnabled($property, $resourceClass) ||
            !$this->isPropertyMapped($property, $resourceClass, true)
        ) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $field = $property;

        $values = $this->normalizeValues((array) $value, $property);
        if (null === $values) {
            return;
        }

        $associations = [];
        if ($this->isPropertyNested($property, $resourceClass)) {
            [$alias, $field, $associations] = $this->addJoinsForNestedProperty($property, $alias, $queryBuilder, $queryNameGenerator, $resourceClass, Join::INNER_JOIN);
        }

        $caseSensitive = true;
        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;

        // prefixing the strategy with i makes it case insensitive
        if (str_starts_with($strategy, 'i')) {
            $strategy = substr($strategy, 1);
            $caseSensitive = false;
        }

        $metadata = $this->getNestedMetadata($resourceClass, $associations);

        if ($metadata->hasField($field)) {
            if ('id' === $field) {
                $values = array_map([$this, 'getIdFromValue'], $values);
            }

            if (!$this->hasValidValues($values, $this->getDoctrineFieldType($property, $resourceClass))) {
                $this->logger->notice('Invalid filter ignored', [
                    'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the doctrine type.', $field)),
                ]);

                return;
            }

            $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $alias, $field, $values, $caseSensitive);

            return;
        }

        // metadata doesn't have the field, nor an association on the field
        if (!$metadata->hasAssociation($field)) {
            return;
        }

        $values = array_map([$this, 'getIdFromValue'], $values);

        $associationResourceClass = $metadata->getAssociationTargetClass($field);
        $associationFieldIdentifier = $metadata->getIdentifierFieldNames()[0];
        $doctrineTypeField = $this->getDoctrineFieldType($associationFieldIdentifier, $associationResourceClass);

        if (!$this->hasValidValues($values, $doctrineTypeField)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Values for field "%s" are not valid according to the doctrine type.', $field)),
            ]);

            return;
        }

        $associationAlias = $alias;
        $associationField = $field;
        if ($metadata->isCollectionValuedAssociation($associationField) || $metadata->isAssociationInverseSide($field)) {
            $associationAlias = QueryBuilderHelper::addJoinOnce($queryBuilder, $queryNameGenerator, $alias, $associationField);
            $associationField = $associationFieldIdentifier;
        }

        $this->addWhereByStrategy($strategy, $queryBuilder, $queryNameGenerator, $associationAlias, $associationField, $values, $caseSensitive);
    }

    /**
     * Creates a function that will wrap a Doctrine expression according to the
     * specified case sensitivity.
     *
     * For example, "o.name" will get wrapped into "LOWER(o.name)" when $caseSensitive
     * is false.
     */
    protected function createWrapCase(bool $caseSensitive): \Closure
    {
        return static function (string $expr) use ($caseSensitive): string {
            if ($caseSensitive) {
                return $expr;
            }

            return sprintf('LOWER(%s)', $expr);
        };
    }

    protected function addWhereByStrategy(string $strategy, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $alias, string $field, $value, bool $caseSensitive): void
    {
        $wrapCase = $this->createWrapCase($caseSensitive);
        $valueParameter = $queryNameGenerator->generateParameterName($field);
        switch ($strategy) {
            case null:
            case self::STRATEGY_EXACT:
                $queryBuilder
                    ->orWhere(sprintf($wrapCase('%s.%s') . ' = ' . $wrapCase(':%s'), $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, $value);
                break;
            case self::STRATEGY_PARTIAL:
                $queryBuilder
                    ->orWhere(sprintf($wrapCase('%s.%s') . ' LIKE ' . $wrapCase('CONCAT(\'%%\', :%s, \'%%\')'), $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, $value);
                break;
            case self::STRATEGY_START:
                $queryBuilder
                    ->orWhere(sprintf($wrapCase('%s.%s') . ' LIKE ' . $wrapCase('CONCAT(:%s, \'%%\')'), $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, $value);
                break;
            case self::STRATEGY_END:
                $queryBuilder
                    ->orWhere(sprintf($wrapCase('%s.%s') . ' LIKE ' . $wrapCase('CONCAT(\'%%\', :%s)'), $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, $value);
                break;
            case self::STRATEGY_WORD_START:
                $queryBuilder
                    ->orWhere(sprintf($wrapCase('%1$s.%2$s') . ' LIKE ' . $wrapCase('CONCAT(:%3$s, \'%%\')') . ' OR ' . $wrapCase('%1$s.%2$s') . ' LIKE ' . $wrapCase('CONCAT(\'%% \', :%3$s, \'%%\')'), $alias, $field, $valueParameter))
                    ->setParameter($valueParameter, $value);
                break;
            default:
                throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy));
        }
    }
}
