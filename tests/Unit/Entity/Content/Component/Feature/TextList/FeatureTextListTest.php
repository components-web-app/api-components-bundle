<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content\Component\Feature\TextList;

use Silverback\ApiComponentBundle\Entity\Component\Feature\TextList\FeatureTextList;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\Range;

class FeatureTextListTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new FeatureTextList();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(Range::class, $constraints['columns']));
    }
}
