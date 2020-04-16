<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Tests\Functional\TestBundle\Form\TestType;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClass;
use Silverback\ApiComponentBundle\Validator\Constraints\FormTypeClassValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class FormTypeClassValidatorTest extends TestCase
{
    private FormTypeClassValidator $formTypeClassValidator;

    /**
     * @var ExecutionContextInterface|MockObject
     */
    private MockObject $executionContextMock;
    /**
     * @var ConstraintViolationBuilderInterface|MockObject
     */
    private MockObject $constraintViolationBuilderMock;

    protected function setUp(): void
    {
        $this->formTypeClassValidator = new FormTypeClassValidator([new TestType()]);

        $this->executionContextMock = $this->getMockBuilder(ExecutionContextInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->constraintViolationBuilderMock = $this->getMockBuilder(ConstraintViolationBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function initializeValidatorForNoErrors(): void
    {
        $this->executionContextMock
            ->expects($this->never())
            ->method('buildViolation');

        $this->constraintViolationBuilderMock
            ->expects($this->never())
            ->method('addViolation');
        $this->formTypeClassValidator->initialize($this->executionContextMock);
    }

    public function test_no_errors_if_no_value(): void
    {
        $this->initializeValidatorForNoErrors();
        $constraint = new FormTypeClass();
        $this->formTypeClassValidator->validate(null, $constraint);
    }

    public function test_exception_thrown_if_value_is_not_a_string(): void
    {
        $this->initializeValidatorForNoErrors();
        $constraint = new FormTypeClass();
        $this->expectException(\InvalidArgumentException::class);
        $this->formTypeClassValidator->validate(new TestType(), $constraint);
    }

    public function test_exception_thrown_if_contraint_not_expected(): void
    {
        $this->initializeValidatorForNoErrors();
        $this->expectException(\InvalidArgumentException::class);
        $this->formTypeClassValidator->validate(TestType::class, new class() extends Constraint {
        });
    }

    public function test_no_errors_if_valid_value(): void
    {
        $this->initializeValidatorForNoErrors();
        $constraint = new FormTypeClass();
        $this->formTypeClassValidator->validate(TestType::class, $constraint);
    }

    public function test_constraint_violation_for_invalid_class(): void
    {
        $constraint = new FormTypeClass();

        $this->executionContextMock
            ->expects($this->once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('setParameter')
            ->with('{{ string }}', __CLASS__)
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('addViolation');
        $this->formTypeClassValidator->initialize($this->executionContextMock);

        $this->formTypeClassValidator->validate(__CLASS__, $constraint);
    }

    public function test_constraint_violation_for_string_that_is_not_a_class(): void
    {
        $constraint = new FormTypeClass();

        $this->executionContextMock
            ->expects($this->once())
            ->method('buildViolation')
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('setParameter')
            ->with('{{ string }}', 'NotAClass')
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects($this->once())
            ->method('addViolation');
        $this->formTypeClassValidator->initialize($this->executionContextMock);

        $this->formTypeClassValidator->validate('NotAClass', $constraint);
    }

    public function test_form_type_class_options_passed_to_parent(): void
    {
        $constraint = new FormTypeClass(['message' => 'different message option']);
        $this->assertEquals('different message option', $constraint->message);
    }
}
