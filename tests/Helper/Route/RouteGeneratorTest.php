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

use ApiPlatform\Validator\Exception\ValidationException;
use Cocur\Slugify\Slugify;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnroutedParentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGenerator;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\Core\RouteRepository;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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
            self::validator(),
        );
    }

    public function test_a_route_is_not_generated_when_the_parent_path_and_the_slug_are_longer_than_the_path_column(): void
    {
        $parent = $this->createPage('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/' . str_repeat('a', 242)));
        $child = $this->createPage('Unnamed Page');
        $child->setParentPage($parent);

        $violations = $this->assertRefusedAsInvalid($child);

        self::assertSame(['path'], $violations);
    }

    public function test_a_route_is_generated_when_the_parent_path_and_the_slug_fill_the_path_column(): void
    {
        $parent = $this->createPage('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/' . str_repeat('a', 241)));
        $child = $this->createPage('Unnamed Page');
        $child->setParentPage($parent);

        $route = $this->generator->create($child);

        self::assertSame(255, \strlen($route->getPath()));
        self::assertSame($route, $child->getRoute());
    }

    public function test_a_route_is_not_generated_when_its_name_is_longer_than_the_name_column(): void
    {
        $page = $this->createPage(str_repeat('b', 256));

        $violations = $this->assertRefusedAsInvalid($page);

        self::assertSame(['path', 'name'], $violations);
    }

    public function test_a_route_is_not_generated_when_its_name_fits_but_its_path_with_the_leading_slash_does_not(): void
    {
        $page = $this->createPage(str_repeat('b', 255));

        $violations = $this->assertRefusedAsInvalid($page);

        self::assertSame(['path'], $violations);
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

    public function test_generating_a_route_fails_clearly_without_an_orm_entity_manager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createStub(ObjectManager::class));
        $generator = new RouteGenerator(new Slugify(), $registry, $this->createStub(TimestampedDataPersister::class), $this->createStub(RouteRepository::class), self::validator());
        $page = $this->createPage('Programme');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for %s', Page::class));
        $generator->create($page);
    }

    public function test_generating_a_route_fails_clearly_without_a_manager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);
        $generator = new RouteGenerator(new Slugify(), $registry, $this->createStub(TimestampedDataPersister::class), $this->createStub(RouteRepository::class), self::validator());
        $page = $this->createPage('Programme');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Could not find entity manager for %s', Page::class));
        $generator->create($page);
    }

    public function test_the_generated_path_of_a_page_with_no_parent_is_its_slug_and_creates_nothing(): void
    {
        $page = $this->createPage('Call For Papers');

        self::assertSame('/call-for-papers', $this->generator->generatePath($page));
        self::assertNull($page->getRoute());
    }

    public function test_the_generated_path_is_prefixed_with_the_parent_route_path_without_conflict_suffixes(): void
    {
        $routeRepository = $this->createStub(RouteRepository::class);
        $routeRepository->method('findConflicts')->willReturn([(new Route())->setName('programme')->setPath('/conference/programme')]);
        $generator = new RouteGenerator(new Slugify(), $this->createStub(ManagerRegistry::class), $this->createStub(TimestampedDataPersister::class), $routeRepository, self::validator());
        $parent = $this->createPageData('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/conference'));
        $child = $this->createPageData('Programme');
        $child->setParentPageData($parent);

        self::assertSame('/conference/programme', $generator->generatePath($child));
    }

    public function test_the_generated_path_matches_the_path_create_gives_when_nothing_conflicts(): void
    {
        $parent = $this->createPage('Conference');
        $parent->setRoute((new Route())->setName('conference')->setPath('/conference'));
        $child = $this->createPage('Programme');
        $child->setParentPage($parent);

        $path = $this->generator->generatePath($child);

        self::assertSame($path, $this->generator->create($child)->getPath());
    }

    public function test_a_redirect_is_named_after_the_path_it_redirects_from(): void
    {
        $target = (new Route())->setName('new')->setPath('/new');

        $redirect = $this->redirectGenerator([])->createRedirect('/old', $target);

        self::assertSame('/old', $redirect->getName());
        self::assertSame('/old', $redirect->getPath());
        self::assertSame($target, $redirect->getRedirect());
    }

    public function test_a_redirect_whose_name_is_taken_keeps_its_path_and_takes_the_first_free_suffix(): void
    {
        $redirect = $this->redirectGenerator(['/old', '/old-1'])->createRedirect('/old', new Route());

        self::assertSame('/old-2', $redirect->getName());
        self::assertSame('/old', $redirect->getPath());
    }

    public function test_a_redirect_from_a_path_filling_the_column_whose_name_is_taken_is_given_a_shortened_name_that_fits(): void
    {
        $path = '/' . str_repeat('a', 254);

        $redirect = $this->redirectGenerator([$path])->createRedirect($path, new Route());

        self::assertSame('/' . str_repeat('a', 252) . '-1', $redirect->getName());
        self::assertSame($path, $redirect->getPath());
        self::assertCount(0, self::validator()->validateProperty($redirect, 'name'));
    }

    public function test_a_shortened_redirect_name_that_is_taken_takes_the_next_suffix(): void
    {
        $path = '/' . str_repeat('a', 254);

        $redirect = $this->redirectGenerator([$path, '/' . str_repeat('a', 252) . '-1'])->createRedirect($path, new Route());

        self::assertSame('/' . str_repeat('a', 252) . '-2', $redirect->getName());
    }

    public function test_a_redirect_is_timestamped_as_new(): void
    {
        $timestamped = $this->createMock(TimestampedDataPersister::class);
        $timestamped->expects(self::once())->method('persistTimestampedFields')->with(self::isInstanceOf(Route::class), true);
        $generator = new RouteGenerator(new Slugify(), $this->createStub(ManagerRegistry::class), $timestamped, $this->routeRepositoryWithNames([]), self::validator());

        $generator->createRedirect('/old', new Route());
    }

    /**
     * @param list<string> $takenNames
     */
    private function redirectGenerator(array $takenNames): RouteGenerator
    {
        return new RouteGenerator(
            new Slugify(),
            $this->createStub(ManagerRegistry::class),
            $this->createStub(TimestampedDataPersister::class),
            $this->routeRepositoryWithNames($takenNames),
            self::validator(),
        );
    }

    /**
     * @param list<string> $takenNames
     */
    private function routeRepositoryWithNames(array $takenNames): RouteRepository
    {
        $routeRepository = $this->createStub(RouteRepository::class);
        $routeRepository->method('count')->willReturnCallback(static fn (array $criteria): int => ['name'] === array_keys($criteria) && \in_array($criteria['name'], $takenNames, true) ? 1 : 0);

        return $routeRepository;
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

    /**
     * @return list<string>
     */
    private function assertRefusedAsInvalid(Page $page): array
    {
        try {
            $this->generator->create($page);
            self::fail('A route was generated that does not fit its columns.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
        }

        self::assertNull($page->getRoute());

        $propertyPaths = [];
        foreach ($violations as $violation) {
            $propertyPaths[] = $violation->getPropertyPath();
        }

        return $propertyPaths;
    }

    private static function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
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
