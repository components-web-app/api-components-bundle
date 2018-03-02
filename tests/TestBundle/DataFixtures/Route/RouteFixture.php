<?php

namespace Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Route;

use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Content\Page;
use Silverback\ApiComponentBundle\Entity\Route\Route;
use Silverback\ApiComponentBundle\Entity\Route\RouteAwareInterface;
use Silverback\ApiComponentBundle\Factory\Entity\Route\RouteFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\DataFixtures\Content\ContentFixture;

class RouteFixture extends AbstractFixture implements DependentFixtureInterface
{
    /**
     * @var RouteFactory
     */
    private $routeFactory;

    public function __construct(
        RouteFactory $routeFactory
    ) {
        $this->routeFactory = $routeFactory;
    }

    public function load(ObjectManager $manager): void
    {
        /** @var Page $childPage */
        $childPage = $this->getReference('childPage');
        $this->createRoute('/child', $childPage);

        $manager->flush();
    }

    private function createRoute(string $route, RouteAwareInterface $content, Route $redirect = null)
    {
        return $this->routeFactory->create(
            [
                'route' => $route,
                'content' => $content,
                'redirect' => $redirect
            ]
        );
    }

    public function getDependencies()
    {
        return [
            ContentFixture::class
        ];
    }
}
