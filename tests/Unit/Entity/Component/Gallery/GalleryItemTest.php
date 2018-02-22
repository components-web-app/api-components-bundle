<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Component\Gallery\GalleryItem;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\NotBlank;

class GalleryItemTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new GalleryItem();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['filePath']));
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['title']));
    }
}
