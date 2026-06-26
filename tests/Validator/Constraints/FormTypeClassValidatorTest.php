<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Form\TestType;
use Silverback\ApiComponentsBundle\Validator\Constraints\FormTypeClass;
use Silverback\ApiComponentsBundle\Validator\Constraints\FormTypeClassValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

/**
 * Concrete stub replacing getMockBuilder for ConstraintViolationBuilderInterface.
 * All fluent methods return `static` which PHPUnit <12.5 cannot mock.
 */
class ConstraintViolationBuilderStub implements ConstraintViolationBuilderInterface
{
    public bool $violationAdded = false;
    public array $parameters = [];

    public function atPath(string $path): static { return $this; }
    public function setParameter(string $key, string $value): static { $this->parameters[$key] = $value; return $this; }
    public function setParameters(array $parameters): static { return $this; }
    public function disableTranslation(): static { return $this; }
    public function setTranslationDomain(string $translationDomain): static { return $this; }
    public function setInvalidValue(mixed $invalidValue): static { return $this; }
    public function setPlural(int $number): static { return $this; }
    public function setCode(?string $code): static { return $this; }
    public function setCause(mixed $cause): static { return $this; }
    public function addViolation(): void { $this->violationAdded = true; }
}

#[AllowMockObjectsWithoutExpectations]
class FormTypeClassValidatorTest extends TestCase
{
    private FormTypeClassValidator $formTypeClassValidator;

    /**
     * @var ExecutionContextInterface|MockObject
     */
    private MockObject $executionContextMock;

    private ConstraintViolationBuilderStub $constraintViolationBuilderStub;

    protected function setUp(): void
    {
        $this->formTypeClassValidator = new FormTypeClassValidator([new TestType()]);

        $this->executionContextMock = $this->getMockBuilder(ExecutionContextInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->constraintViolationBuilderStub = new ConstraintViolationBuilderStub();
    }

    private function initializeValidatorForNoErrors(): void
    {
        $this->executionContextMock
            ->expects($this->never())
            ->method('buildViolation');
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
        $this->expectException(InvalidArgumentException::class);
        $this->formTypeClassValidator->validate(new TestType(), $constraint);
    }

    public function test_exception_thrown_if_contraint_not_expected(): void
    {
        $this->initializeValidatorForNoErrors();
        $this->expectException(InvalidArgumentException::class);
        $this->formTypeClassValidator->validate(
            TestType::class,
            new class extends Constraint {
            }
        );
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
            ->expects(self::once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->constraintViolationBuilderStub);

        $this->formTypeClassValidator->initialize($this->executionContextMock);
        $this->formTypeClassValidator->validate(__CLASS__, $constraint);

        $this->assertTrue($this->constraintViolationBuilderStub->violationAdded);
        $this->assertSame(__CLASS__, $this->constraintViolationBuilderStub->parameters['{{ string }}']);
    }

    public function test_constraint_violation_for_string_that_is_not_a_class(): void
    {
        $constraint = new FormTypeClass();

        $this->executionContextMock
            ->expects(self::once())
            ->method('buildViolation')
            ->willReturn($this->constraintViolationBuilderStub);

        $this->formTypeClassValidator->initialize($this->executionContextMock);
        $this->formTypeClassValidator->validate('NotAClass', $constraint);

        $this->assertTrue($this->constraintViolationBuilderStub->violationAdded);
        $this->assertSame('NotAClass', $this->constraintViolationBuilderStub->parameters['{{ string }}']);
    }

    public function test_form_type_class_options_passed_to_parent(): void
    {
        $constraint = new FormTypeClass(message: 'different message option');
        $this->assertEquals('different message option', $constraint->message);
    }
}
