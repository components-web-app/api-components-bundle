<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\SortableTrait;

class SortableTraitTest extends TestCase
{
    /**
     * @var MockObject|SortableTrait
     */
    private $sortableMock;

    public function setUp()
    {
        $mockA = $this->getMockForTrait(SortableTrait::class);
        $mockA->setSort(2);
        $mockB = $this->getMockForTrait(SortableTrait::class);
        $mockB->setSort(3);
        $collection = new ArrayCollection([$mockA, $mockB]);

        $this->sortableMock = $this->getMockForTrait(SortableTrait::class);
        $this->sortableMock
            ->method('getSortCollection')
            ->willReturn($collection)
        ;
    }

    public function test_get_set()
    {
        $this->sortableMock->setSort(10);
        $this->assertEquals(10, $this->sortableMock->getSort());
    }

    public function test_calculate_sort()
    {
        $this->assertEquals(0, $this->sortableMock->calculateSort(null));
        $this->assertEquals(4, $this->sortableMock->calculateSort(true));
        $this->assertEquals(1, $this->sortableMock->calculateSort(false));
    }
}
