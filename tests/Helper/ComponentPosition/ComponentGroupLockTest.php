<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentPosition;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\Decorator\EntityManagerDecorator;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\TransactionRequiredException;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\PropagateUpdatesListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentGroupLock;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

class ComponentGroupLockTest extends TestCase
{
    use PublishableDatabaseTrait;

    private RecordingEntityManager $recording;
    private ComponentGroupLock $lock;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->recording = new RecordingEntityManager($this->entityManager);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->recording);
        $this->lock = new ComponentGroupLock($registry);
    }

    public function test_the_group_row_is_locked_for_writing_before_its_positions_are_read(): void
    {
        $group = $this->group('main', [0, 1]);

        $this->lock->transactional(fn () => $this->lock->lockedPositions($group));

        self::assertSame([['lock', $group, LockMode::PESSIMISTIC_WRITE], ['createQueryBuilder']], $this->recording->calls);
    }

    public function test_the_lock_needs_a_transaction(): void
    {
        $group = $this->group('main', [0]);

        $this->expectException(TransactionRequiredException::class);

        $this->lock->lockedPositions($group);
    }

    public function test_the_positions_are_read_fresh_and_sorted_by_sort_value_then_id(): void
    {
        $group = $this->group('main', [5, 5, 0]);
        $this->group('other', [1]);
        $this->entityManager->getConnection()->executeStatement('UPDATE _acb_component_position SET sort_value = 9 WHERE sort_value = 0');

        $positions = $this->lock->transactional(fn () => $this->lock->lockedPositions($group));

        self::assertSame([5, 5, 9], array_map(static fn (ComponentPosition $position) => $position->sortValue, $positions));
        self::assertLessThan(0, strcmp((string) $positions[0]->getId(), (string) $positions[1]->getId()));
        foreach ($positions as $position) {
            self::assertSame($group, $position->componentGroup);
        }
    }

    public function test_a_position_written_in_this_request_keeps_its_changes(): void
    {
        $group = $this->group('main', [0, 1]);
        [$touched, $other] = $this->positionsOf($group);
        $touched->pageDataProperty = 'changed';
        $this->entityManager->getConnection()->executeStatement('UPDATE _acb_component_position SET page_data_property = \'stored\'');

        $positions = $this->lock->transactional(fn () => $this->lock->lockedPositions($group, [$touched]));

        self::assertSame([$touched, $other], $positions);
        self::assertSame('changed', $touched->pageDataProperty);
        self::assertSame('stored', $other->pageDataProperty);
    }

    public function test_a_position_written_in_this_request_from_another_group_is_not_in_the_group(): void
    {
        $group = $this->group('main', [0]);
        $other = $this->group('other', [0]);
        [$moved] = $this->positionsOf($other);
        $moved->componentGroup = $group;
        $new = new ComponentPosition();
        $new->componentGroup = $group;

        $positions = $this->lock->transactional(fn () => $this->lock->lockedPositions($group, [$moved, $new]));

        self::assertSame($this->positionsOf($group), $positions);
    }

    public function test_a_group_not_stored_yet_has_no_positions_and_is_not_locked(): void
    {
        $group = new ComponentGroup();
        $group->reference = 'new';
        $group->location = 'new';
        $this->entityManager->persist($group);

        $positions = $this->lock->transactional(fn () => $this->lock->lockedPositions($group));

        self::assertSame([], $positions);
        self::assertSame([], $this->recording->calls);
    }

    public function test_a_transaction_is_committed(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('CREATE TABLE marker (id INTEGER)');

        $result = $this->lock->transactional(static function () use ($connection) {
            $connection->executeStatement('INSERT INTO marker (id) VALUES (1)');

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertFalse($connection->isTransactionActive());
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM marker'));
    }

    public function test_a_transaction_is_rolled_back_and_the_error_rethrown(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('CREATE TABLE marker (id INTEGER)');
        $error = new \RuntimeException('failed');

        try {
            $this->lock->transactional(static function () use ($connection, $error) {
                $connection->executeStatement('INSERT INTO marker (id) VALUES (1)');
                throw $error;
            });
            self::fail('The error was not rethrown');
        } catch (\RuntimeException $caught) {
            self::assertSame($error, $caught);
        }

        self::assertFalse($connection->isTransactionActive());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM marker'));
        self::assertTrue($this->entityManager->isOpen());
    }

    public function test_propagation_is_held_until_the_transaction_commits(): void
    {
        $connection = $this->entityManager->getConnection();
        $propagation = $this->createMock(PropagateUpdatesListener::class);
        $propagation->expects(self::once())->method('hold');
        $propagation->expects(self::once())->method('release')->willReturnCallback(function (object $entityManager, bool $committed) use ($connection): void {
            self::assertSame($this->recording, $entityManager);
            self::assertTrue($committed);
            self::assertFalse($connection->isTransactionActive());
        });

        $this->lockWith($propagation)->transactional(static function () use ($connection): void {
            self::assertTrue($connection->isTransactionActive());
        });
    }

    public function test_propagation_is_discarded_when_the_transaction_rolls_back(): void
    {
        $propagation = $this->createMock(PropagateUpdatesListener::class);
        $propagation->expects(self::once())->method('hold');
        $propagation->expects(self::once())->method('release')->with($this->recording, false);

        $this->expectException(\RuntimeException::class);

        $this->lockWith($propagation)->transactional(static function (): void {
            throw new \RuntimeException('failed');
        });
    }

    private function lockWith(PropagateUpdatesListener $propagation): ComponentGroupLock
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->recording);

        return new ComponentGroupLock($registry, $propagation);
    }

    public function test_a_registry_without_an_orm_entity_manager_is_refused(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(InvalidArgumentException::class);

        (new ComponentGroupLock($registry))->transactional(static fn () => null);
    }

    /**
     * @param list<int> $sortValues
     */
    private function group(string $reference, array $sortValues): ComponentGroup
    {
        $group = new ComponentGroup();
        $group->reference = $reference;
        $group->location = $reference;
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);
        foreach ($sortValues as $sortValue) {
            $component = new DummyComponent();
            $this->entityManager->persist($component);
            $position = new ComponentPosition();
            $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
            $position->componentGroup = $group;
            $position->component = $component;
            $position->sortValue = $sortValue;
            $this->entityManager->persist($position);
        }
        $this->entityManager->flush();
        $this->recording->calls = [];

        return $group;
    }

    /**
     * @return list<ComponentPosition>
     */
    private function positionsOf(ComponentGroup $group): array
    {
        return $this->entityManager->getRepository(ComponentPosition::class)->findBy(['componentGroup' => $group], ['sortValue' => 'ASC', 'id' => 'ASC']);
    }
}

class RecordingEntityManager extends EntityManagerDecorator
{
    /** @var list<array<mixed>> */
    public array $calls = [];

    public function lock(object $entity, LockMode|int $lockMode, \DateTimeInterface|int|null $lockVersion = null): void
    {
        $this->calls[] = ['lock', $entity, $lockMode];
        parent::lock($entity, $lockMode, $lockVersion);
    }

    public function createQueryBuilder(): QueryBuilder
    {
        $this->calls[] = ['createQueryBuilder'];

        return parent::createQueryBuilder();
    }
}
