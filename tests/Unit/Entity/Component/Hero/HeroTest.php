<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\NotNull;

class HeroTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new Hero();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['title']));
    }
}
