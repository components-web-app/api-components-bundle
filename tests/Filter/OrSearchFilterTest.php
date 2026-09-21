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
use ApiPlatform\Metadata\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
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
        $classMetadata->method('hasField')->willReturnCallback(static fn (string $field): bool => \in_array($field, ['field1', 'field2'], true));
        $classMetadata->method('hasAssociation')->willReturn(false);
        $classMetadata->method('getTypeOfField')->willReturn('string');

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

    private function createFilter(array $properties): OrSearchFilter
    {
        return new OrSearchFilter($this->managerRegistry, $this->createStub(IriConverterInterface::class), null, null, $properties);
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
