<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content\Component\Hero;

use Silverback\ApiComponentBundle\Entity\Component\Hero\Hero;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\NotNull;

class HeroTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new Hero();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['title']));
    }
}
