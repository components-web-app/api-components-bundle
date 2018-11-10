<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content\Component\Article;

use Silverback\ApiComponentBundle\Entity\Content\Page\Dynamic\ArticlePage;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\NotNull;

class ArticlePageTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new ArticlePage();
        $constraints = $this->getConstraints($entity);
        // $this->assertTrue($this->instanceInArray(Image::class, $constraints['filePath']));
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['title']));
        $this->assertTrue($this->instanceInArray(NotNull::class, $constraints['content']));
    }
}
