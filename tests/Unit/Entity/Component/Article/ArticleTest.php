<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Article;

use Silverback\ApiComponentBundle\Entity\Component\Article\Article;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotNull;

class ArticleTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new Article();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(Image::class, $constraints['filePath']));
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['title']));
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['content']));
    }
}
