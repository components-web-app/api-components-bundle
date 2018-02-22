<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Factory\Fixtures\Component;

use Silverback\ApiComponentBundle\Exception\InvalidFactoryOptionException;
use Silverback\ApiComponentBundle\Factory\Fixtures\Component\FormFactory;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestHandler;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;

class FormFactoryTest extends AbstractFactoryTest
{
    /**
     * @var FormFactory
     */
    private $componentFactory;

    public function setUp()
    {
        $this->componentFactory = new FormFactory(...$this->getConstructorArgs());
    }

    public function test_invalid_option()
    {
        $this->expectException(InvalidFactoryOptionException::class);
        $this->componentFactory->create(
            [
                'invalid' => null
            ]
        );
    }

    public function test_create()
    {
        $ops = [
            'formType' => TestType::class,
            'successHandler' => TestHandler::class
        ];
        $component = $this->componentFactory->create($ops);
        $this->assertEquals($ops['formType'], $component->getFormType());
        $this->assertEquals($ops['successHandler'], $component->getSuccessHandler());
    }
}
