<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Article;

use Silverback\ApiComponentBundle\Entity\Component\Content\Content;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\NotNull;

class ContentTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $content = new Content();
        $constraints = $this->getConstraints($content);
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['content']));
    }
}
