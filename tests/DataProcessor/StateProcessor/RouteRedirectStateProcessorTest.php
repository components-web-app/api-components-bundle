<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RouteRedirectStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RouteRedirectStateProcessorTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation}>
     */
    public static function pathChanges(): iterable
    {
        yield 'PATCH' => [new Patch()];
        yield 'PUT' => [new Put()];
    }

    #[DataProvider('pathChanges')]
    public function test_a_changed_path_gets_a_redirect_from_the_previous_path_after_the_write(Operation $operation): void
    {
        $route = (new Route())->setPath('/new');
        $redirect = new Route();
        $calls = [];
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'write';

            return 'written';
        });
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::once())->method('createRedirect')->with('/old', $route)->willReturn($redirect);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($redirect)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'redirect';
        });
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('getRepository');

        $result = (new RouteRedirectStateProcessor($inner, $generator, $this->registry($entityManager), self::validator()))
            ->process($route, $operation, [], ['previous_data' => (new Route())->setPath('/old')]);

        self::assertSame('written', $result);
        self::assertSame(['write', 'redirect'], $calls);
    }

    public function test_cascading_rewrites_routed_descendants_and_redirects_their_old_paths(): void
    {
        $parentPage = new Page();
        $route = (new Route())->setPath('/new')->setPage($parentPage);
        $route->cascadeChildPaths = true;
        $childRoute = (new Route())->setPath('/old/child')->setName('/old/child');
        $child = (new Page())->setRoute($childRoute);
        $elsewhere = (new Page())->setRoute((new Route())->setPath('/other/child'));

        $pages = $this->createStub(EntityRepository::class);
        $pages->method('findBy')->willReturnCallback(static fn (array $criteria): array => ($criteria['parentPage'] ?? null) === $parentPage ? [$child, $elsewhere] : []);
        $pageData = $this->createStub(EntityRepository::class);
        $pageData->method('findBy')->willReturn([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Page::class, $pages],
            [AbstractPageData::class, $pageData],
        ]);
        $entityManager->expects(self::exactly(2))->method('flush');
        $persisted = [];
        $entityManager->method('persist')->willReturnCallback(static function (object $object) use (&$persisted): void {
            $persisted[] = $object;
        });
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('createRedirect')->willReturnCallback(static fn (string $from, Route $to): Route => (new Route())->setPath($from)->setRedirect($to));

        (new RouteRedirectStateProcessor($this->inner(), $generator, $this->registry($entityManager), self::validator()))
            ->process($route, new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);

        self::assertSame('/new/child', $childRoute->getPath());
        self::assertSame('/new/child', $childRoute->getName());
        self::assertSame(['/old', '/old/child'], array_map(static fn (Route $redirect): string => $redirect->getPath(), $persisted));
    }

    /**
     * @return iterable<string, array{mixed, Operation, mixed}>
     */
    public static function ignored(): iterable
    {
        yield 'an unchanged path' => [(new Route())->setPath('/same'), new Patch(), (new Route())->setPath('/same')];
        yield 'a created route' => [(new Route())->setPath('/new'), new Post(), (new Route())->setPath('/old')];
        yield 'a deleted route' => [(new Route())->setPath('/new'), new Delete(), (new Route())->setPath('/old')];
        yield 'no previous route' => [(new Route())->setPath('/new'), new Patch(), null];
        yield 'something other than a route' => [new Page(), new Patch(), (new Route())->setPath('/old')];
        yield 'an operation that is not HTTP' => [(new Route())->setPath('/new'), new Mutation(), (new Route())->setPath('/old')];
    }

    #[DataProvider('ignored')]
    public function test_nothing_is_redirected_for(mixed $data, Operation $operation, mixed $previousData): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::never())->method('createRedirect');

        self::assertSame('written', (new RouteRedirectStateProcessor($this->inner(), $generator, $registry, self::validator()))->process($data, $operation, [], ['previous_data' => $previousData]));
    }

    public function test_a_route_class_without_an_orm_manager_is_a_configuration_error(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createStub(ObjectManager::class));

        $this->expectException(InvalidArgumentException::class);
        (new RouteRedirectStateProcessor($this->inner(), $this->createStub(RouteGeneratorInterface::class), $registry, self::validator()))
            ->process((new Route())->setPath('/new'), new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $route = new Route();
        $operation = new Post();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($route, $operation, ['id' => 1], ['previous_data' => null]);

        (new RouteRedirectStateProcessor($inner, $this->createStub(RouteGeneratorInterface::class), $this->createStub(ManagerRegistry::class), self::validator()))
            ->process($route, $operation, ['id' => 1], ['previous_data' => null]);
    }

    public function test_a_cascade_that_would_make_descendant_paths_too_long_is_refused_before_anything_is_written(): void
    {
        $parentPage = new Page();
        $route = (new Route())->setPath('/' . str_repeat('n', 20))->setPage($parentPage);
        $route->cascadeChildPaths = true;
        $childPath = '/old/' . str_repeat('c', 235);
        $childRoute = (new Route())->setPath($childPath)->setName($childPath);
        $child = (new Page())->setRoute($childRoute);
        $grandchildRoute = (new Route())->setPath($childPath . '/g')->setName($childPath . '/g');
        $grandchild = (new Page())->setRoute($grandchildRoute);

        $entityManager = $this->hierarchy([[$parentPage, $child], [$child, $grandchild]]);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::never())->method('process');
        $generator = $this->createMock(RouteGeneratorInterface::class);
        $generator->expects(self::never())->method('createRedirect');

        try {
            (new RouteRedirectStateProcessor($inner, $generator, $this->registry($entityManager), self::validator()))
                ->process($route, new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);
            self::fail('A cascade that makes a descendant path too long was written.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
        }

        self::assertCount(2, $violations);
        $newChildPath = '/' . str_repeat('n', 20) . '/' . str_repeat('c', 235);
        self::assertSame('path', $violations[0]->getPropertyPath());
        self::assertSame($route, $violations[0]->getRoot());
        self::assertSame($newChildPath, $violations[0]->getInvalidValue());
        self::assertSame(Length::TOO_LONG_ERROR, $violations[0]->getCode());
        self::assertInstanceOf(Length::class, $violations[0]->getConstraint());
        self::assertStringStartsWith(\sprintf('The path of the descendant route "%s" would become "%s". This value is too long.', $childPath, $newChildPath), (string) $violations[0]->getMessage());
        self::assertSame($newChildPath . '/g', $violations[1]->getInvalidValue());
        self::assertSame($childPath, $childRoute->getPath());
        self::assertSame($childPath . '/g', $grandchildRoute->getPath());
    }

    public function test_a_cascade_that_fills_the_path_column_is_written(): void
    {
        $parentPage = new Page();
        $route = (new Route())->setPath('/new')->setPage($parentPage);
        $route->cascadeChildPaths = true;
        $childPath = '/old/' . str_repeat('c', 250);
        $childRoute = (new Route())->setPath($childPath)->setName($childPath);
        $child = (new Page())->setRoute($childRoute);

        $entityManager = $this->hierarchy([[$parentPage, $child]]);
        $entityManager->expects(self::exactly(2))->method('flush');
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('createRedirect')->willReturn(new Route());

        (new RouteRedirectStateProcessor($this->inner(), $generator, $this->registry($entityManager), self::validator()))
            ->process($route, new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);

        self::assertSame('/new/' . str_repeat('c', 250), $childRoute->getPath());
    }

    public function test_a_cascade_to_a_path_under_the_old_path_stops_when_an_unrouted_child_page_loops_back(): void
    {
        $parentPage = new Page();
        $route = (new Route())->setPath('/old/inner')->setName('/old/inner')->setPage($parentPage);
        $parentPage->setRoute($route);
        $route->cascadeChildPaths = true;
        $unrouted = new Page();

        $entityManager = $this->hierarchy([[$parentPage, $unrouted], [$unrouted, $parentPage]]);
        $entityManager->expects(self::once())->method('flush');
        $persisted = $this->recordPersisted($entityManager);

        (new RouteRedirectStateProcessor($this->inner(), $this->redirectingGenerator(), $this->registry($entityManager), self::validator()))
            ->process($route, new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);

        self::assertSame('/old/inner', $route->getPath());
        self::assertSame(['/old'], array_map(static fn (Route $redirect): string => $redirect->getPath(), $persisted->getArrayCopy()));
    }

    public function test_a_descendant_reached_through_two_parents_is_moved_once_with_every_other_descendant(): void
    {
        $parentPage = new Page();
        $route = (new Route())->setPath('/new')->setPage($parentPage);
        $route->cascadeChildPaths = true;
        $childRoute = (new Route())->setPath('/old/child')->setName('/old/child');
        $child = (new Page())->setRoute($childRoute);
        $grandchildRoute = (new Route())->setPath('/old/child/grandchild')->setName('/old/child/grandchild');
        $grandchild = (new Page())->setRoute($grandchildRoute);
        $unrouted = new Page();
        $underUnroutedRoute = (new Route())->setPath('/old/under-unrouted')->setName('/old/under-unrouted');
        $underUnrouted = (new Page())->setRoute($underUnroutedRoute);

        $entityManager = $this->hierarchy([
            [$parentPage, $child],
            [$parentPage, $unrouted],
            [$unrouted, $child],
            [$unrouted, $underUnrouted],
            [$child, $grandchild],
        ]);
        $entityManager->expects(self::exactly(3))->method('flush');
        $persisted = $this->recordPersisted($entityManager);

        (new RouteRedirectStateProcessor($this->inner(), $this->redirectingGenerator(), $this->registry($entityManager), self::validator()))
            ->process($route, new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);

        self::assertSame('/new/child', $childRoute->getPath());
        self::assertSame('/new/child/grandchild', $grandchildRoute->getPath());
        self::assertSame('/new/under-unrouted', $underUnroutedRoute->getPath());
        self::assertSame(
            ['/old', '/old/child', '/old/child/grandchild', '/old/under-unrouted'],
            array_map(static fn (Route $redirect): string => $redirect->getPath(), $persisted->getArrayCopy()),
        );
    }

    /**
     * @param list<array{Page, Page}> $edges
     */
    private function hierarchy(array $edges): EntityManagerInterface&MockObject
    {
        $pages = $this->createStub(EntityRepository::class);
        $pages->method('findBy')->willReturnCallback(static function (array $criteria) use ($edges): array {
            $children = [];
            foreach ($edges as [$parent, $child]) {
                if (($criteria['parentPage'] ?? null) === $parent) {
                    $children[] = $child;
                }
            }

            return $children;
        });
        $pageData = $this->createStub(EntityRepository::class);
        $pageData->method('findBy')->willReturn([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturnMap([
            [Page::class, $pages],
            [AbstractPageData::class, $pageData],
        ]);

        return $entityManager;
    }

    /**
     * @return \ArrayObject<int, Route>
     */
    private function recordPersisted(EntityManagerInterface&MockObject $entityManager): \ArrayObject
    {
        $persisted = new \ArrayObject();
        $entityManager->method('persist')->willReturnCallback(static function (Route $redirect) use ($persisted): void {
            $persisted->append($redirect);
        });

        return $persisted;
    }

    private function redirectingGenerator(): RouteGeneratorInterface
    {
        $generator = $this->createStub(RouteGeneratorInterface::class);
        $generator->method('createRedirect')->willReturnCallback(static fn (string $from, Route $to): Route => (new Route())->setPath($from)->setRedirect($to));

        return $generator;
    }

    private static function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }

    private function registry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return $registry;
    }
}
