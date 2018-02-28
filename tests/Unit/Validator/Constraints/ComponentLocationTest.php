<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Validator\Contraints;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Validator\Constraints\ComponentLocation;
use Symfony\Component\Validator\Constraint;

class ComponentLocationTest extends TestCase
{
    public function test_class_constraint(): void
    {
        $componentLocation = new ComponentLocation();
        $this->assertEquals(Constraint::CLASS_CONSTRAINT, $componentLocation->getTargets());
    }
}
