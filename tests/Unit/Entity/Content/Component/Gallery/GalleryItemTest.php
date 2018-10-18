<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content\Component\Gallery;

use Silverback\ApiComponentBundle\Entity\Content\Component\Gallery\GalleryItem;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\NotBlank;

class GalleryItemTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new GalleryItem();
        $constraints = $this->getConstraints($entity);
        // $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['filePath']));
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['title']));
    }
}
