<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Feature\Columns;

use Silverback\ApiComponentBundle\Entity\Component\Feature\Columns\FeatureColumns;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Symfony\Component\Validator\Constraints\Range;

class FeatureColumnsTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new FeatureColumns();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(Range::class, $constraints['columns']));
    }
}
