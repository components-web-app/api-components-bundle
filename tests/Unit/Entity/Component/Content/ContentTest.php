<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Content;

use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\NotNull;

class ContentTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new Content();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['content']));
    }
}
