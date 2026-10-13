<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\HttpCache\Warm;

use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\RouteExtension;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteAncestorGateResolver;
use Silverback\ApiComponentsBundle\HttpCache\Warm\PublicRoutePaths;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

class PublicRoutePathsTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_lists_the_routes_the_public_collection_lists_shortest_path_first(): void
    {
        $this->route('/about/team');
        $this->route('/zebra');
        $this->route('/');
        $this->route('/about');
        $this->route('/draft', null);
        $this->route('/scheduled', new \DateTimeImmutable('+1 day'));
        $this->route('/was-scheduled', new \DateTimeImmutable('-1 day'));
        $this->route('/user-area/profile');
        $draftParent = $this->routedPage('/gated', null);
        $child = $this->routedPage('/gated/child');
        $child->setParentPage($draftParent);
        $this->entityManager->flush();

        self::assertSame(['/', '/about', '/zebra', '/about/team', '/was-scheduled'], $this->paths()->find(null));
    }

    public function test_a_limit_keeps_the_shortest_paths(): void
    {
        $this->route('/about/team');
        $this->route('/');
        $this->route('/about');
        $this->entityManager->flush();

        self::assertSame(['/', '/about'], $this->paths()->find(2));
    }

    private function paths(): PublicRoutePaths
    {
        $accessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $accessChecker->method('isGranted')->willReturn(false);
        $extension = new RouteExtension(
            [['route' => '/user-area*', 'security' => "is_granted('ROLE_USER')"]],
            $accessChecker,
            new RouteAncestorGateResolver($this->registry),
        );

        return new PublicRoutePaths($this->registry, $extension);
    }

    private function route(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('-1 minute')): Route
    {
        $route = (new Route())->setPath($path)->setName($path)->setLiveAt($liveAt);
        $route->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($route);

        return $route;
    }

    private function routedPage(string $path, ?\DateTimeImmutable $liveAt = new \DateTimeImmutable('-1 minute')): Page
    {
        $page = new Page();
        $page->reference = $path;
        $page->isTemplate = false;
        $page->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $route = $this->route($path, $liveAt);
        $route->setPage($page);
        $page->setRoute($route);
        $this->entityManager->persist($page);

        return $page;
    }
}
