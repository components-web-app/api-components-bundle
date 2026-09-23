<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Filter;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Filter\OrSearchFilter;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyOrSearchFilterable;

class OrSearchFilterTest extends TestCase
{
    private const EXISTING_PREDICATE = 'o.publishedAt IS NOT NULL';

    private EntityManagerInterface $entityManager;
    private ManagerRegistry $managerRegistry;

    protected function setUp(): void
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('hasField')->willReturnCallback(static fn (string $field): bool => \in_array($field, ['field1', 'field2', 'rank'], true));
        $classMetadata->method('hasAssociation')->willReturnCallback(static fn (string $field): bool => 'owner' === $field);
        $classMetadata->method('getTypeOfField')->willReturnCallback(static fn (string $field): string => \in_array($field, ['rank', 'id'], true) ? Types::INTEGER : Types::STRING);
        $classMetadata->method('getAssociationTargetClass')->willReturn(DummyOrSearchFilterable::class);
        $classMetadata->method('getIdentifierFieldNames')->willReturn(['id']);

        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $this->entityManager->method('getExpressionBuilder')->willReturn(new Expr());

        $this->managerRegistry = $this->createStub(ManagerRegistry::class);
        $this->managerRegistry->method('getManagerForClass')->willReturn($this->entityManager);
    }

    public static function strategyProvider(): iterable
    {
        foreach (['exact', 'iexact', 'partial', 'ipartial', 'start', 'istart', 'end', 'iend', 'word_start', 'iword_start'] as $strategy) {
            yield $strategy . ' with a single value' => [$strategy, 'alpha'];
            yield $strategy . ' with multiple values' => [$strategy, ['alpha', 'gamma']];
        }
    }

    #[DataProvider('strategyProvider')]
    public function test_clauses_are_combined_with_or_among_themselves_and_and_against_the_query(string $strategy, string|array $value): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => $strategy])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => $value]]);

        $dql = $queryBuilder->getDQL();

        self::assertStringContainsString(self::EXISTING_PREDICATE . ' AND', $dql);
        self::assertStringNotContainsString(self::EXISTING_PREDICATE . ' OR', $dql);
    }

    public function test_clauses_for_different_fields_are_combined_with_or_within_a_single_and(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => 'exact', 'field2' => 'exact'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'alpha', 'field2' => 'alpha']]);

        self::assertSame(self::EXISTING_PREDICATE . ' AND (o.field1 = :field1_p10 OR o.field2 = :field2_p20)', $this->getWhere($queryBuilder));
    }

    public function test_multiple_values_for_one_field_are_combined_with_or_within_a_single_and(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => 'exact'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => ['alpha', 'gamma']]]);

        self::assertSame(self::EXISTING_PREDICATE . ' AND (o.field1 = :field1_p10 OR o.field1 = :field1_p11)', $this->getWhere($queryBuilder));
    }

    public function test_a_word_start_clause_is_parenthesised_so_it_cannot_capture_a_preceding_predicate(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => 'word_start'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'alpha']]);

        self::assertSame(self::EXISTING_PREDICATE . " AND (o.field1 LIKE CONCAT(:field1_p10, '%') OR o.field1 LIKE CONCAT('% ', :field1_p10, '%'))", $this->getWhere($queryBuilder));
    }

    public function test_a_case_insensitive_partial_clause_ands_against_the_query(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => 'ipartial'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'alpha']]);

        self::assertSame(self::EXISTING_PREDICATE . " AND LOWER(o.field1) LIKE LOWER(CONCAT('%', :field1_p10, '%'))", $this->getWhere($queryBuilder));
    }

    public function test_a_filter_with_no_matching_property_leaves_the_query_untouched(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['field1' => 'exact'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['unmapped' => 'alpha']]);

        self::assertSame(self::EXISTING_PREDICATE, $this->getWhere($queryBuilder));
    }

    public function test_clauses_from_one_request_do_not_leak_into_the_next(): void
    {
        $filter = $this->createFilter(['field1' => 'exact']);

        $first = $this->createQueryBuilder();
        $filter->apply($first, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'alpha']]);

        $second = $this->createQueryBuilder();
        $filter->apply($second, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'gamma']]);

        self::assertSame(self::EXISTING_PREDICATE . ' AND o.field1 = :field1_p10', $this->getWhere($second));
    }

    public static function invalidValueProvider(): iterable
    {
        yield 'a field' => ['rank', 'rank'];
        yield 'an association identifier' => ['owner', 'owner'];
    }

    #[DataProvider('invalidValueProvider')]
    public function test_a_value_invalid_for_the_doctrine_type_is_logged_and_ignored(string $property, string $field): void
    {
        $logged = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('notice')->willReturnCallback(static function (string $message, array $context) use (&$logged): void {
            $logged[] = [$message, $context];
        });

        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter([$property => 'exact', 'field1' => 'exact'], $logger)->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => [$property => 'not-an-integer', 'field1' => 'alpha']]);

        self::assertSame(self::EXISTING_PREDICATE . ' AND o.field1 = :field1_p10', $this->getWhere($queryBuilder));
        self::assertSame('Invalid filter ignored', $logged[0][0]);
        self::assertInstanceOf(InvalidArgumentException::class, $logged[0][1]['exception']);
        self::assertSame(\sprintf('Values for field "%s" are not valid according to the doctrine type.', $field), $logged[0][1]['exception']->getMessage());
    }

    public function test_a_valid_integer_value_is_applied(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $this->createFilter(['rank' => 'exact'])->apply($queryBuilder, new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['rank' => '3']]);

        self::assertSame(self::EXISTING_PREDICATE . ' AND o.rank = :rank_p10', $this->getWhere($queryBuilder));
    }

    public function test_an_unknown_strategy_is_an_invalid_argument(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('strategy bogus does not exist.');

        $this->createFilter(['field1' => 'bogus'])->apply($this->createQueryBuilder(), new QueryNameGenerator(), DummyOrSearchFilterable::class, null, ['filters' => ['field1' => 'alpha']]);
    }

    private function createFilter(array $properties, ?LoggerInterface $logger = null): OrSearchFilter
    {
        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getResourceFromIri')->willThrowException(new InvalidArgumentException());

        return new OrSearchFilter($this->managerRegistry, $iriConverter, null, $logger, $properties);
    }

    private function createQueryBuilder(): QueryBuilder
    {
        return (new QueryBuilder($this->entityManager))
            ->select('o')
            ->from(DummyOrSearchFilterable::class, 'o')
            ->andWhere(self::EXISTING_PREDICATE);
    }

    private function getWhere(QueryBuilder $queryBuilder): string
    {
        return (string) $queryBuilder->getDQLPart('where');
    }
}
