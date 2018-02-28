<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Feature\Stacked;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Stacked\FeatureStackedItem;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\NotBlank;

class FeatureStackedItemTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new FeatureStackedItem();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['description']));
    }
}
