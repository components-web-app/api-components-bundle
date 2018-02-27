<?php

namespace Silverback\ApiComponentBundle\Tests\Unit\Entity\Component\Form;

use Silverback\ApiComponentBundle\Entity\Component\Form\FormView;
use Silverback\ApiComponentBundle\Tests\TestBundle\Form\TestType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FormViewTest extends TypeTestCase
{
    private $validator;

    /**
     * @var FormView
     */
    private $formView;

    public function setUp()
    {
        parent::setUp();
        $form = $this->factory->create(TestType::class);
        $this->formView = new FormView($form->createView());
    }

    protected function getExtensions(): array
    {
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->validator
            ->method('validate')
            ->will($this->returnValue(new ConstraintViolationList()));
        $this->validator
            ->method('getMetadataFor')
            ->will($this->returnValue(new ClassMetadata(Form::class)));

        return array(
            new ValidatorExtension($this->validator),
        );
    }

    public function test_method_rendered()
    {
        $this->assertEquals(false, $this->formView->isMethodRendered());
    }

    public function test_rendered()
    {
        $this->assertEquals(false, $this->formView->isRendered());
    }

    public function test_children()
    {
        $this->assertCount(1, $this->formView->getChildren());
    }

    public function test_vars()
    {
        $expected = [
            'errors' => [],
            'attr' => [
                'novalidate' => 'novalidate'
            ],
            'id' => 'test',
            'name' => 'test',
            'full_name' => 'test',
            'disabled' => false,
            'block_prefixes' => [
                0 => 'form',
                1 => 'test',
                2 => '_test'
            ],
            'unique_block_prefix' => '_test',
            'valid' => true,
            'required' => true,
            'label_attr' => [],
            'submitted' => false,
            'action' => ''
        ];
        $this->assertEquals($expected, $this->formView->getVars());
    }
}
