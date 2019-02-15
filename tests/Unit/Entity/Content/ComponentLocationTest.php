<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Content;

use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntity;
use Symfony\Component\Validator\Constraints\NotBlank;

class ComponentLocationTest extends AbstractEntity
{
    public function test_constraints()
    {
        $entity = new \Silverback\ApiComponentBundle\Entity\Component\ComponentLocation();
        $constraints = $this->getConstraints($entity);
        // I SHOULD BE TESTING TE RESULT OF VALIDATION ON AN ENTITY NOT THAT THE CONSTRAINT EXISTS GENERALLY SPEAKING
        // MISSING THE CHECK ON THE CLASS VALIDATION
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['component']));
    }
}
