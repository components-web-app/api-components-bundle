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
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RouteRedirectStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\Route\RouteGeneratorInterface;

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

        $result = (new RouteRedirectStateProcessor($inner, $generator, $this->registry($entityManager)))
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

        (new RouteRedirectStateProcessor($this->inner(), $generator, $this->registry($entityManager)))
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

        self::assertSame('written', (new RouteRedirectStateProcessor($this->inner(), $generator, $registry))->process($data, $operation, [], ['previous_data' => $previousData]));
    }

    public function test_a_route_class_without_an_orm_manager_is_a_configuration_error(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createStub(ObjectManager::class));

        $this->expectException(InvalidArgumentException::class);
        (new RouteRedirectStateProcessor($this->inner(), $this->createStub(RouteGeneratorInterface::class), $registry))
            ->process((new Route())->setPath('/new'), new Patch(), [], ['previous_data' => (new Route())->setPath('/old')]);
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $route = new Route();
        $operation = new Post();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($route, $operation, ['id' => 1], ['previous_data' => null]);

        (new RouteRedirectStateProcessor($inner, $this->createStub(RouteGeneratorInterface::class), $this->createStub(ManagerRegistry::class)))
            ->process($route, $operation, ['id' => 1], ['previous_data' => null]);
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
