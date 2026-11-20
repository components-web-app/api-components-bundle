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
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\RoutePrefixAttributeReader;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RoutableMoveStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPage;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\Route\RouteCascadePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RouteMovePlanner;
use Silverback\ApiComponentsBundle\Helper\Route\RoutePrefixResolver;
use Silverback\ApiComponentsBundle\Tests\Helper\Route\RouteHierarchyStubTrait;

class RoutableMoveStateProcessorTest extends TestCase
{
    use RouteHierarchyStubTrait;

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function writes(): iterable
    {
        yield 'PATCH' => [new Patch()];
        yield 'PUT' => [new Put()];
    }

    #[DataProvider('writes')]
    public function test_a_moved_page_is_written_then_its_route_is_rewritten_redirected_and_flushed(Operation $operation): void
    {
        $blog = self::routed(new Page(), '/blog');
        $news = self::routed(new Page(), '/news');
        $post = self::routed((new Page())->setParentPage($news), '/blog/post');
        $entityManager = $this->mockHierarchy([$blog, $news, $post]);
        $this->original($entityManager, ['parentPage' => $blog, 'parentPageData' => null]);
        $calls = [];
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($post, $operation, ['id' => 1], ['k' => 'v'])->willReturnCallback(static function () use (&$calls, $post): string {
            $calls[] = ['write', self::routeOf($post)->getPath()];

            return 'written';
        });
        $entityManager->expects(self::exactly(2))->method('flush')->willReturnCallback(static function () use (&$calls, $post): void {
            $calls[] = ['flush', self::routeOf($post)->getPath()];
        });
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(static function (Route $redirect) use (&$calls): void {
            $calls[] = ['redirect', $redirect->getPath()];
        });

        $result = $this->processor($inner, $entityManager)->process($post, $operation, ['id' => 1], ['k' => 'v']);

        self::assertSame('written', $result);
        self::assertSame([['write', '/blog/post'], ['flush', '/news/post'], ['redirect', '/blog/post'], ['flush', '/news/post']], $calls);
    }

    public function test_a_move_that_plans_nothing_writes_only_the_page(): void
    {
        $blog = self::routed(new Page(), '/blog');
        $section = (new Page())->setParentPage($blog);
        $post = self::routed((new Page())->setParentPage($section), '/blog/post');
        $entityManager = $this->mockHierarchy([$blog, $section, $post]);
        $this->original($entityManager, ['parentPage' => $blog, 'parentPageData' => null]);
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('persist');

        self::assertSame('written', $this->processor($this->inner(), $entityManager)->process($post, new Patch()));
    }

    /**
     * @return iterable<string, array{\Closure(Page, AbstractPageData): AbstractPage, \Closure(Page, AbstractPageData): array<string, mixed>, string}>
     */
    public static function changedParents(): iterable
    {
        yield 'a new parent page' => [
            static fn (Page $taken, AbstractPageData $old): AbstractPage => self::routed((new Page())->setParentPage($taken), '/blog/post'),
            static fn (Page $taken, AbstractPageData $old): array => ['parentPage' => null, 'parentPageData' => null],
            'parentPage',
        ];
        yield 'a new parent page data' => [
            static fn (Page $taken, AbstractPageData $old): AbstractPage => self::routed((new Page())->setParentPageData($old), '/blog/post'),
            static fn (Page $taken, AbstractPageData $old): array => ['parentPage' => null, 'parentPageData' => null],
            'parentPageData',
        ];
        yield 'a parent page swapped for a parent page data' => [
            static fn (Page $taken, AbstractPageData $old): AbstractPage => self::routed((new Page())->setParentPageData($old), '/blog/post'),
            static fn (Page $taken, AbstractPageData $old): array => ['parentPage' => $taken, 'parentPageData' => null],
            'parentPageData',
        ];
        yield 'a parent page data swapped for a parent page' => [
            static fn (Page $taken, AbstractPageData $old): AbstractPage => self::routed((new Page())->setParentPage($taken), '/blog/post'),
            static fn (Page $taken, AbstractPageData $old): array => ['parentPage' => null, 'parentPageData' => $old],
            'parentPage',
        ];
        yield 'a parent page data removed' => [
            static fn (Page $taken, AbstractPageData $old): AbstractPage => self::routed(new Page(), '/blog/post'),
            static fn (Page $taken, AbstractPageData $old): array => ['parentPage' => null, 'parentPageData' => $old],
            'parentPageData',
        ];
    }

    /**
     * @param \Closure(Page, AbstractPageData): AbstractPage         $moved
     * @param \Closure(Page, AbstractPageData): array<string, mixed> $original
     */
    #[DataProvider('changedParents')]
    public function test_a_move_to_a_taken_path_is_refused_on_the_changed_parent_field_before_anything_is_written(\Closure $moved, \Closure $original, string $field): void
    {
        $news = self::routed(new Page(), '/news');
        $old = self::routed(new class extends AbstractPageData {}, '/news');
        $page = $moved($news, $old);
        $entityManager = $this->mockHierarchy([$news, $page], ['/news/post', '/news/blog/post', '/post']);
        $this->original($entityManager, $original($news, $old));
        $entityManager->expects(self::never())->method('flush');
        $entityManager->expects(self::never())->method('persist');
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::never())->method('process');

        try {
            $this->processor($inner, $entityManager)->process($page, new Patch());
            self::fail('A move to a taken path was written.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
        }

        self::assertCount(1, $violations);
        self::assertSame($field, $violations[0]->getPropertyPath());
        self::assertSame($page, $violations[0]->getRoot());
        self::assertSame('/blog/post', self::routeOf($page)->getPath());
    }

    /**
     * @return iterable<string, array{mixed, Operation}>
     */
    public static function ignored(): iterable
    {
        yield 'something other than a page' => [new Route(), new Patch()];
        yield 'a created page' => [new Page(), new Post()];
        yield 'a deleted page' => [new Page(), new Delete()];
        yield 'an operation that is not HTTP' => [new Page(), new Mutation()];
    }

    #[DataProvider('ignored')]
    public function test_nothing_is_planned_for(mixed $data, Operation $operation): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        self::assertSame('written', $this->processor($this->inner(), null, $registry)->process($data, $operation));
    }

    public function test_a_page_class_without_an_orm_manager_is_only_written(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->createStub(ObjectManager::class));

        self::assertSame('written', $this->processor($this->inner(), null, $registry)->process(new Page(), new Patch()));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unmoved(): iterable
    {
        yield 'a page the unit of work does not know' => [[]];
        yield 'an unchanged parent' => [['parentPage' => null, 'parentPageData' => null, 'title' => 'old']];
    }

    /**
     * @param array<string, mixed> $original
     */
    #[DataProvider('unmoved')]
    public function test_a_page_that_has_not_moved_is_only_written(array $original): void
    {
        $page = self::routed(new Page(), '/post');
        $entityManager = $this->mockHierarchy([$page], ['/post']);
        $this->original($entityManager, $original);
        $entityManager->expects(self::never())->method('flush');

        self::assertSame('written', $this->processor($this->inner(), $entityManager)->process($page, new Patch()));
    }

    public function test_a_page_the_unit_of_work_does_not_know_is_only_written_whatever_its_parent(): void
    {
        $news = self::routed(new Page(), '/news');
        $page = self::routed((new Page())->setParentPage($news), '/post');
        $entityManager = $this->mockHierarchy([$news, $page]);
        $this->original($entityManager, []);
        $entityManager->expects(self::never())->method('flush');

        self::assertSame('written', $this->processor($this->inner(), $entityManager)->process($page, new Patch()));
        self::assertSame('/post', self::routeOf($page)->getPath());
    }

    /**
     * @param array<string, mixed> $original
     */
    private function original(EntityManagerInterface&MockObject $entityManager, array $original): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn($original);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
    }

    private function processor(ProcessorInterface $inner, ?EntityManagerInterface $entityManager, ?ManagerRegistry $registry = null): RoutableMoveStateProcessor
    {
        if (null === $registry) {
            $registry = $this->createStub(ManagerRegistry::class);
            $registry->method('getManagerForClass')->willReturn($entityManager);
        }
        $cascadePlanner = new RouteCascadePlanner($this->redirectingGenerator(), self::validator());

        return new RoutableMoveStateProcessor(
            $inner,
            $registry,
            new RouteMovePlanner(new RoutePrefixResolver(new RoutePrefixAttributeReader($this->createStub(ManagerRegistry::class))), $cascadePlanner),
            $cascadePlanner,
        );
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
