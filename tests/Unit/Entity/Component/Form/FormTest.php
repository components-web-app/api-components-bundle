<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form\Form;
use Silverback\ApiComponentBundle\Tests\Unit\Entity\AbstractEntityTest;
use Silverback\ApiComponentBundle\Validator\Constraints\FormHandlerClass;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClass;
use Symfony\Component\Validator\Constraints\NotBlank;

class FormTest extends AbstractEntityTest
{
    public function test_constraints()
    {
        $entity = new Form();
        $constraints = $this->getConstraints($entity);
        $this->assertTrue($this->instanceInArray(FormTypeClass::class, $constraints['formType']));
        $this->assertTrue($this->instanceInArray(NotBlank::class, $constraints['formType']));
        $this->assertTrue($this->instanceInArray(FormHandlerClass::class, $constraints['successHandler']));
    }
}
