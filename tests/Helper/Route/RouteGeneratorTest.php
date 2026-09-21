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

use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGenerator;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;

class RouteGeneratorTest extends TestCase
{
    private RouteGenerator $generator;

    protected function setUp(): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn([]);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);
        $routeRepository = $this->createStub(RouteRepository::class);
        $routeRepository->method('findConflicts')->willReturn([]);

        $this->generator = new RouteGenerator(
            new Slugify(),
            $registry,
            $this->createStub(TimestampedDataPersister::class),
            $routeRepository,
        );
    }

    public function test_a_page_with_no_parent_is_given_a_top_level_path(): void
    {
        $page = $this->createPage('Programme');

        $route = $this->generator->create($page);

        self::assertSame('/programme', $route->getPath());
        self::assertSame('programme', $route->getName());
        self::assertSame($route, $page->getRoute());
    }

    public function test_a_page_whose_parent_page_has_a_route_is_given_a_path_under_the_parent_path(): void
    {
        $parent = $this->createPage('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/conference'));
        $child = $this->createPage('Programme');
        $child->setParentPage($parent);

        $route = $this->generator->create($child);

        self::assertSame('/conference/programme', $route->getPath());
    }

    public function test_a_page_whose_parent_page_data_has_a_route_is_given_a_path_under_the_parent_path(): void
    {
        $parent = $this->createPageData('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/conference'));
        $child = $this->createPage('Programme');
        $child->setParentPageData($parent);

        $route = $this->generator->create($child);

        self::assertSame('/conference/programme', $route->getPath());
    }

    public function test_a_route_is_not_generated_for_a_page_whose_parent_page_has_no_route(): void
    {
        $parent = $this->createPage('Conference');
        $child = $this->createPage('2027');
        $child->setParentPage($parent);

        $this->assertRefusedForMissingParentRoute($child);
    }

    public function test_a_route_is_not_generated_for_a_page_whose_parent_page_data_has_no_route(): void
    {
        $parent = $this->createPageData('Conference');
        $child = $this->createPageData('2027');
        $child->setParentPageData($parent);

        $this->assertRefusedForMissingParentRoute($child);
    }

    private function assertRefusedForMissingParentRoute(Page|AbstractPageData $child): void
    {
        try {
            $this->generator->create($child);
            self::fail('A route was generated for a page whose parent has no route.');
        } catch (UnroutedParentException $exception) {
            self::assertStringContainsString('parent page has no route', $exception->getMessage());
        }

        self::assertNull($child->getRoute());
    }

    private function createPage(string $title): Page
    {
        $page = new Page();
        $page->setTitle($title);

        return $page;
    }

    private function createPageData(string $title): AbstractPageData
    {
        $pageData = new class extends AbstractPageData {};
        $pageData->setTitle($title);

        return $pageData;
    }
}
