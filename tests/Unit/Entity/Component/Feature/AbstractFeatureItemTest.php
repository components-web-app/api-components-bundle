<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Feature;

use Silverback\ApiComponentBundle\Entity\Component\Feature\AbstractFeatureItem;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class AbstractFeatureItemTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = $this->getMockForAbstractClass(AbstractFeatureItem::class);
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['label']));
        $this->assertTrue($this->instanceInArray(Url::class, $constraints['link']));
    }
}
