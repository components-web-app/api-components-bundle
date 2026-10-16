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

use ApiPlatform\Metadata\Patch;
use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\ApiResource\ComponentGroupOrder;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentGroupOrderStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ComponentGroupOrderStateProvider;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ListedComponentPositions;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ComponentGroupOrderStateProcessorTest extends TestCase
{
    use ComponentPositionDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_the_listed_order_is_stored_and_returned(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 10, 'C' => 20]);

        $order = $this->processor()->process($this->input(['/position/B', '/position/A', '/position/C']), new Patch(), ['id' => 'main']);

        self::assertSame('/group/main', $order?->componentGroup);
        self::assertSame(['/position/B' => 10, '/position/A' => 11, '/position/C' => 20], $order->sortValues->getArrayCopy());
        self::assertSame(['/position/B', '/position/A', '/position/C'], $order->componentPositions);
        self::assertSame('B10 A11 C20', $this->stored($group));
    }

    public function test_a_position_that_no_longer_exists_is_ignored(): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 10]);

        $order = $this->processor()->process($this->input(['/position/deleted', '/position/B', '/position/A']), new Patch(), ['id' => 'main']);

        self::assertSame(['/position/B' => 10, '/position/A' => 11], $order?->sortValues->getArrayCopy());
        self::assertSame('B10 A11', $this->stored($group));
    }

    public function test_an_unlisted_position_follows_its_predecessor_and_is_in_the_response(): void
    {
        $group = $this->group('main', ['A' => 0, 'N' => 5, 'B' => 10]);

        $order = $this->processor()->process($this->input(['/position/B', '/position/A']), new Patch(), ['id' => 'main']);

        self::assertSame(['/position/B' => 10, '/position/A' => 11, '/position/N' => 12], $order?->sortValues->getArrayCopy());
        self::assertSame('B10 A11 N12', $this->stored($group));
    }

    /**
     * @return iterable<string, array{list<string>, string, string}>
     */
    public static function refusedOrders(): iterable
    {
        yield 'a position of another group' => [['/position/B', '/position/X', '/position/A'], 'componentPositions[1]', 'The position `/position/X` is not in the component group `main`.'];
        yield 'a position listed twice' => [['/position/B', '/position/A', '/position/B'], 'componentPositions[2]', 'The position `/position/B` is listed more than once.'];
        yield 'an iri that is not a position' => [['/group/main', '/position/A'], 'componentPositions[0]', 'The position `/group/main` is not in the component group `main`.'];
        yield 'an iri that does not resolve' => [['/position/A', '/nope'], 'componentPositions[1]', 'The position `/nope` is not in the component group `main`.'];
    }

    /**
     * @param list<string> $iris
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedOrders')]
    public function test_a_refused_order_changes_nothing(array $iris, string $path, string $message): void
    {
        $group = $this->group('main', ['A' => 0, 'B' => 10]);
        $this->group('other', ['X' => 0]);

        try {
            $this->processor()->process($this->input($iris), new Patch(), ['id' => 'main']);
            self::fail('The order was not refused');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            self::assertCount(1, $violations);
            self::assertSame($path, $violations->get(0)->getPropertyPath());
            self::assertSame($message, $violations->get(0)->getMessage());
            self::assertSame($group, $violations->get(0)->getRoot());
        }

        self::assertSame('A0 B10', $this->stored($group));
    }

    public function test_every_refusal_is_reported(): void
    {
        $this->group('main', ['A' => 0, 'B' => 10]);
        $this->group('other', ['X' => 0]);

        try {
            $this->processor()->process($this->input(['/position/X', '/position/A', '/position/A']), new Patch(), ['id' => 'main']);
            self::fail('The order was not refused');
        } catch (ValidationException $exception) {
            self::assertCount(2, $exception->getConstraintViolationList());
        }
    }

    public function test_a_group_that_does_not_exist_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor()->process($this->input([]), new Patch(), ['id' => 'missing']);
    }

    public function test_an_id_that_is_not_a_string_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor()->process($this->input([]), new Patch(), []);
    }

    public function test_data_that_is_not_an_order_lists_nothing(): void
    {
        $this->group('main', ['A' => 5, 'B' => 5]);

        $order = $this->processor()->process(null, new Patch(), ['id' => 'main']);

        self::assertSame([5, 6], array_values($order?->sortValues->getArrayCopy() ?? []));
    }

    private function processor(): ComponentGroupOrderStateProcessor
    {
        $iriConverter = $this->iriConverter();
        $repository = new ComponentGroupRepository($this->registry);

        return new ComponentGroupOrderStateProcessor(
            $repository,
            new ComponentPositionOrderer(),
            $this->resolver(),
            new ComponentGroupOrderStateProvider($repository, new ListedComponentPositions($iriConverter), new ComponentPositionOrderer(), $iriConverter),
            $this->registry,
        );
    }

    /**
     * @param list<string> $iris
     */
    private function input(array $iris): ComponentGroupOrder
    {
        return new ComponentGroupOrder('main', '/group/main', $iris, new \ArrayObject());
    }
}
