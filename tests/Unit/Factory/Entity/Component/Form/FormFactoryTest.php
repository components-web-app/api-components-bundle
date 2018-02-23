<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Entity\Component\Form;

use Silverback\ApiComponentBundle\Factory\Entity\Component\Form\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;
use Silverback\ApiComponentBundle\Tests\Unit\Factory\AbstractFactory;

class FormFactoryTest extends AbstractFactory
{
    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->className = FormFactory::class;
        $this->testOps = [
            'formType' => TestType::class,
            'successHandler' => TestHandler::class
        ];
        parent::setUp();
    }
}
