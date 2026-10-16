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
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentPositionPlacementStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentGroupLock;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;

class ComponentPositionPlacementStateProcessorTest extends TestCase
{
    use ComponentPositionDatabaseTrait;

    private bool $transactionWhilePersisting = false;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_a_new_position_with_no_anchor_goes_at_the_end(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 10]);
        $position = $this->newPosition($group);

        $result = $this->processor()->process($position, new Post());

        self::assertSame('done', $result);
        self::assertTrue($this->transactionWhilePersisting);
        self::assertSame(11, $position->sortValue);
        self::assertSame('A0 B10 new11', $this->stored($group));
    }

    public function test_a_new_position_goes_after_its_anchor(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 1]);
        $position = $this->newPosition($group);
        $position->after = '/position/A';

        $this->processor()->process($position, new Post());

        self::assertSame('A0 new1 B2', $this->stored($group));
    }

    public function test_a_new_position_goes_before_its_anchor(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 1]);
        $position = $this->newPosition($group);
        $position->before = '/position/A';

        $this->processor()->process($position, new Post());

        self::assertSame('new0 A1 B2', $this->stored($group));
    }

    public function test_positions_created_with_a_component_are_placed_in_turn(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 1]);
        $other = $this->group('other', ['X' => 0]);
        $component = new DummyComponent();
        $first = $this->newPosition($group);
        $first->after = '/position/A';
        $second = $this->newPosition($group);
        $second->after = '/position/A';
        $elsewhere = $this->newPosition($other);
        $component->setComponentPositions([$first, $second, $elsewhere]);
        $this->entityManager->persist($component);

        $this->processor()->process($component, new Post());

        self::assertSame([1, 2, 1], [$second->sortValue, $first->sortValue, $elsewhere->sortValue]);
        self::assertSame('A0 new1 new2 B3', $this->stored($group));
        self::assertSame('X0 new1', $this->stored($other));
    }

    public function test_a_position_moved_into_another_group_is_placed_in_it(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 10]);
        $this->group('other', ['X' => 0]);
        $moved = $this->iris['/position/X'];
        self::assertInstanceOf(ComponentPosition::class, $moved);
        $moved->componentGroup = $group;

        $this->processor()->process($moved, new Patch());

        self::assertSame('A0 B10 X11', $this->stored($group));
    }

    public function test_a_position_moved_within_its_group_by_an_anchor(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 1, 'C' => 2]);
        $moved = $this->iris['/position/C'];
        self::assertInstanceOf(ComponentPosition::class, $moved);
        $moved->before = '/position/A';

        $this->processor()->process($moved, new Patch());

        self::assertSame('C2 A3 B4', $this->stored($group));
    }

    public function test_a_position_written_without_a_move_is_not_placed(): void
    {
        $this->group('main', ['A' => 5, 'B' => 5]);
        $position = $this->iris['/position/A'];

        $this->processor(true)->process($position, new Patch());

        self::assertFalse($this->transactionWhilePersisting);
    }

    public function test_an_anchor_gone_from_the_group_by_the_time_it_is_locked_is_refused(): void
    {
        $group = $this->group('main', ['A' => 0]);
        $this->group('other', ['X' => 0]);
        $position = $this->newPosition($group);
        $position->after = '/position/X';

        try {
            $this->processor(true)->process($position, new Post());
            self::fail('The anchor was not refused');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            self::assertSame('after', $violation->getPropertyPath());
            self::assertSame('The position `/position/X` is not in the component group `main`.', $violation->getMessage());
            self::assertSame('/position/X', $violation->getInvalidValue());
        }
        self::assertFalse($this->entityManager->getConnection()->isTransactionActive());
        self::assertSame('A0', $this->stored($group));
    }

    /**
     * @return iterable<string, array{Operation, mixed}>
     */
    public static function untouched(): iterable
    {
        yield 'a delete' => [new Delete(), new ComponentPosition()];
        yield 'an operation that is not HTTP' => [new class extends Operation {}, new ComponentPosition()];
        yield 'a resource that holds no positions' => [new Post(), new \stdClass()];
        yield 'a component with no positions' => [new Post(), new DummyComponent()];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('untouched')]
    public function test_nothing_is_placed_for(Operation $operation, mixed $data): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');
        $decorated = $this->createMock(ProcessorInterface::class);
        $decorated->expects(self::once())->method('process')->with($data, $operation)->willReturn('done');

        $processor = new ComponentPositionPlacementStateProcessor($decorated, $registry, new ComponentGroupLock($registry), new ComponentPositionOrderer(), $this->resolver());

        self::assertSame('done', $processor->process($data, $operation));
    }

    public function test_a_registry_without_an_orm_entity_manager_is_refused(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);
        $processor = new ComponentPositionPlacementStateProcessor($this->createStub(ProcessorInterface::class), $registry, new ComponentGroupLock($registry), new ComponentPositionOrderer(), $this->resolver());

        $this->expectException(\LogicException::class);

        $processor->process(new ComponentPosition(), new Post());
    }

    private function processor(bool $flushOnly = false): ComponentPositionPlacementStateProcessor
    {
        $decorated = $this->createStub(ProcessorInterface::class);
        $decorated->method('process')->willReturnCallback(function (mixed $data) use ($flushOnly): string {
            $this->transactionWhilePersisting = $this->entityManager->getConnection()->isTransactionActive();
            if (!$flushOnly) {
                $this->entityManager->persist($data);
            }
            $this->entityManager->flush();

            return 'done';
        });

        return new ComponentPositionPlacementStateProcessor($decorated, $this->registry, new ComponentGroupLock($this->registry), new ComponentPositionOrderer(), $this->resolver());
    }

    private function newPosition(\Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup $group): ComponentPosition
    {
        $position = new ComponentPosition();
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position->componentGroup = $group;
        $position->component = new DummyComponent();
        $this->entityManager->persist($position->component);

        return $position;
    }
}
