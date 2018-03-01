<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Content\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\Range;

class FeatureColumnsTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new FeatureColumns();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(Range::class, $constraints['columns']));
    }
}
