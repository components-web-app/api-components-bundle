<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Fixture\Generator\Plan;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\ExecutionOrder;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodKind;
use Silverback\ApiComponentsBundle\Fixture\Generator\Plan\MethodPlan;

class ExecutionOrderTest extends TestCase
{
    public function test_top_level_methods_run_in_build_order_before_any_nested_method(): void
    {
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $page = $this->method(MethodKind::Page, 'page', $this->method(MethodKind::Page, 'pageChild'));
        $redirects = new MethodPlan(MethodKind::Redirects, 'redirects');

        self::assertSame(['layout', 'page', 'redirects', 'pageChild'], $this->names(new ExecutionOrder([$layout, $page, $redirects])));
    }

    public function test_page_data_closures_run_before_page_closures_in_each_pass(): void
    {
        $page = $this->method(MethodKind::Page, 'page', $this->method(MethodKind::Page, 'pageChild'));
        $pageData = $this->method(MethodKind::PageData, 'pageData', $this->method(MethodKind::PageData, 'pageDataChild', $this->method(MethodKind::Page, 'grandchild')));

        self::assertSame(['page', 'pageData', 'pageDataChild', 'pageChild', 'grandchild'], $this->names(new ExecutionOrder([$page, $pageData])));
    }

    public function test_a_nested_page_under_a_nested_page_runs_in_the_next_pass(): void
    {
        $first = $this->method(MethodKind::Page, 'first', $this->method(MethodKind::Page, 'firstChild', $this->method(MethodKind::Page, 'firstGrandchild')));
        $second = $this->method(MethodKind::Page, 'second', $this->method(MethodKind::Page, 'secondChild'));

        self::assertSame(['first', 'second', 'firstChild', 'secondChild', 'firstGrandchild'], $this->names(new ExecutionOrder([$first, $second])));
    }

    public function test_runs_after_compares_positions_and_is_false_for_the_same_method(): void
    {
        $child = new MethodPlan(MethodKind::Page, 'child');
        $page = $this->method(MethodKind::Page, 'page', $child);
        $layout = new MethodPlan(MethodKind::Layout, 'layout');
        $order = new ExecutionOrder([$layout, $page]);

        self::assertTrue($order->runsAfter($child, $layout));
        self::assertTrue($order->runsAfter($page, $layout));
        self::assertFalse($order->runsAfter($layout, $page));
        self::assertFalse($order->runsAfter($page, $page));
    }

    private function method(MethodKind $kind, string $name, MethodPlan ...$children): MethodPlan
    {
        $method = new MethodPlan($kind, $name);
        $method->children = array_values($children);

        return $method;
    }

    /**
     * @return list<string>
     */
    private function names(ExecutionOrder $order): array
    {
        return array_map(static fn (MethodPlan $method): string => $method->name, $order->methods);
    }
}
