<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Route;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;

class RouteAncestorGateResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{bool}>
     */
    public static function managersWithoutTheOrm(): iterable
    {
        yield 'no manager' => [false];
        yield 'a manager that is not the ORM' => [true];
    }

    #[DataProvider('managersWithoutTheOrm')]
    public function test_nothing_is_gated_without_an_orm_entity_manager(bool $hasManager): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($hasManager ? $this->createStub(ObjectManager::class) : null);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects(self::never())->method('andWhere');
        $queryBuilder->expects(self::never())->method('setParameter');
        $queryNameGenerator = $this->createMock(QueryNameGeneratorInterface::class);
        $queryNameGenerator->expects(self::never())->method('generateParameterName');

        $resolver = new RouteAncestorGateResolver($registry);
        $resolver->andWhereNotGated($queryBuilder, $queryNameGenerator, 'o');

        self::assertSame([], $resolver->getGatedRouteIds());
    }
}
