<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Entity\Core;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractPageData;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Concrete Page subclass that exposes an ID setter for tests.
 * AbstractPage::$id is protected (from IdTrait) so we set it from within the hierarchy.
 */
class TestPage extends Page
{
    public function withId(UuidInterface $id): static
    {
        $this->id = $id;

        return $this;
    }
}

/**
 * Concrete AbstractPageData subclass for testing the parentPageData violation field name.
 */
class TestPageData extends AbstractPageData
{
    public Page $page;

    public function withId(UuidInterface $id): static
    {
        $this->id = $id;

        return $this;
    }
}

class AbstractPageTest extends TestCase
{
    private function makePage(?string $uuid = null): TestPage
    {
        $page = new TestPage();
        if ($uuid) {
            $page->withId(Uuid::fromString($uuid));
        }

        return $page;
    }

    private function makeContext(int $expectedViolations = 0, ?string $expectedField = null): ExecutionContextInterface
    {
        $context = $this->createMock(ExecutionContextInterface::class);

        if (0 === $expectedViolations) {
            $context->expects($this->never())->method('buildViolation');

            return $context;
        }

        $builder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $builder->expects($this->exactly($expectedViolations))->method('atPath')->with($expectedField)->willReturn($builder);
        $builder->expects($this->exactly($expectedViolations))->method('addViolation');
        $context->expects($this->exactly($expectedViolations))->method('buildViolation')->willReturn($builder);

        return $context;
    }

    public function test_no_parent_produces_no_violation(): void
    {
        $page = $this->makePage('11111111-1111-1111-1111-111111111111');
        $page->validateNoCircularParent($this->makeContext(0));
    }

    public function test_self_reference_via_parent_page_triggers_violation(): void
    {
        $page = $this->makePage('11111111-1111-1111-1111-111111111111');
        $page->setParentPage($page);

        $page->validateNoCircularParent($this->makeContext(1, 'parentPage'));
    }

    public function test_indirect_cycle_via_parent_page_triggers_violation(): void
    {
        $a = $this->makePage('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $b = $this->makePage('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');

        $b->setParentPage($a);
        $a->setParentPage($b);

        $a->validateNoCircularParent($this->makeContext(1, 'parentPage'));
    }

    public function test_valid_chain_produces_no_violation(): void
    {
        $a = $this->makePage('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');
        $b = $this->makePage('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $c = $this->makePage('cccccccc-cccc-cccc-cccc-cccccccccccc');

        $c->setParentPage($b);
        $b->setParentPage($a);

        $c->validateNoCircularParent($this->makeContext(0));
    }

    public function test_new_page_without_id_with_parent_produces_no_violation(): void
    {
        $parent = $this->makePage('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $newPage = $this->makePage(); // no ID — not yet persisted

        $newPage->setParentPage($parent);

        $newPage->validateNoCircularParent($this->makeContext(0));
    }

    public function test_cycle_via_parent_page_data_reports_correct_field(): void
    {
        $pageData = new TestPageData();
        $pageData->withId(Uuid::fromString('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa'));
        $pageData->setParentPageData($pageData);

        $pageData->validateNoCircularParent($this->makeContext(1, 'parentPageData'));
    }

    public function test_get_parent_page_route_returns_parent_route(): void
    {
        $route = new Route();
        $parent = $this->makePage('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $parent->setRoute($route);

        $child = $this->makePage('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $child->setParentPage($parent);

        $this->assertSame($route, $child->getParentPageRoute());
    }

    public function test_get_parent_page_route_returns_null_when_parent_has_no_route(): void
    {
        $parent = $this->makePage('bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
        $child = $this->makePage('cccccccc-cccc-cccc-cccc-cccccccccccc');
        $child->setParentPage($parent);

        $this->assertNull($child->getParentPageRoute());
    }

    public function test_get_parent_page_route_returns_null_when_no_parent(): void
    {
        $page = $this->makePage('aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa');

        $this->assertNull($page->getParentPageRoute());
    }
}
