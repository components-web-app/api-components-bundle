<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

class RouteExtensionTest extends TestCase
{
    use PublishableDatabaseTrait;

    private const string USER = "is_granted('ROLE_USER')";

    private const string ADMIN = "is_granted('ROLE_ADMIN')";

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        foreach (['/public', '/user-area', '/user-area/page', '/user-are', '/admin-area/page'] as $path) {
            $this->persistRoute($path, new \DateTimeImmutable('2000-01-01T00:00:00+00:00'));
        }
        $this->persistRoute('/draft', null);
        $this->persistRoute('/scheduled', new \DateTimeImmutable('2999-01-01T00:00:00+00:00'));
        $this->entityManager->flush();
    }

    public function test_an_anonymous_caller_lists_live_routes_outside_every_route_security_rule(): void
    {
        $config = [['route' => '/user-area*', 'security' => self::USER], ['route' => '/admin-area*', 'security' => self::ADMIN]];

        self::assertSame(['/public', '/user-are'], $this->paths($config, []));
    }

    public function test_a_caller_satisfying_a_rule_lists_the_routes_under_it(): void
    {
        $config = [['route' => '/user-area*', 'security' => self::USER], ['route' => '/admin-area*', 'security' => self::ADMIN]];

        self::assertSame(['/public', '/user-are', '/user-area', '/user-area/page'], $this->paths($config, [self::USER]));
    }

    public function test_a_caller_with_the_publication_permission_lists_routes_which_are_not_live(): void
    {
        self::assertSame(['/admin-area/page', '/draft', '/public', '/scheduled', '/user-are', '/user-area', '/user-area/page'], $this->paths(null, [self::ADMIN]));
    }

    public function test_an_anonymous_caller_lists_every_live_route_when_no_route_security_is_configured(): void
    {
        self::assertSame(['/admin-area/page', '/public', '/user-are', '/user-area', '/user-area/page'], $this->paths(null, []));
    }

    public function test_another_resource_class_is_left_alone(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(Page::class, 'o');
        $dql = $queryBuilder->getDQL();

        $this->extension([['route' => '/user-area*', 'security' => self::USER]], [])->applyToCollection($queryBuilder, new QueryNameGenerator(), Page::class);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    /**
     * @param list<string> $grantedExpressions
     *
     * @return list<string>
     */
    private function paths(?array $config, array $grantedExpressions): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(Route::class, 'o');
        $this->extension($config, $grantedExpressions)->applyToCollection($queryBuilder, new QueryNameGenerator(), Route::class);
        $paths = array_map(static fn (Route $route): string => $route->getPath(), $queryBuilder->getQuery()->getResult());
        sort($paths);

        return $paths;
    }

    /**
     * @param list<string> $grantedExpressions
     */
    private function extension(?array $config, array $grantedExpressions): RouteExtension
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturnCallback(static fn (string $class, string $expression): bool => \in_array($expression, $grantedExpressions, true));

        return new RouteExtension($config, $accessChecker, new RouteAncestorGateResolver($this->registry));
    }

    private function persistRoute(string $path, ?\DateTimeImmutable $liveAt): void
    {
        $route = (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
        $route->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($route);
    }
}
