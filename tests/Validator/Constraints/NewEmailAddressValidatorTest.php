<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddress;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

class NewEmailAddressValidatorTest extends TestCase
{
    private NewEmailAddressValidator $newEmailAddressValidator;
    /**
     * @var UserRepository|MockObject
     */
    private $repositoryMock;
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
        $this->repositoryMock = $this->createMock(UserRepository::class);
        $this->newEmailAddressValidator = new NewEmailAddressValidator($this->repositoryMock);

        $this->executionContextMock = $this->getMockBuilder(ExecutionContextInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->constraintViolationBuilderMock = $this->getMockBuilder(ConstraintViolationBuilderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function test_exception_thrown_for_incorrect_user_class(): void
    {
        $this->expectException(UnexpectedTypeException::class);
        $constraint = new class() extends Constraint {
        };
        $dummyUser = new class() {
        };
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }

    public function test_no_constraint_errors_if_no_new_email_address(): void
    {
        $this->executionContextMock
            ->expects($this->never())
            ->method('buildViolation');

        $this->constraintViolationBuilderMock
            ->expects($this->never())
            ->method('addViolation');
        $this->newEmailAddressValidator->initialize($this->executionContextMock);

        $constraint = new NewEmailAddress();
        $dummyUser = new class() extends AbstractUser {
        };
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }

    public function test_error_if_new_email_is_same_as_previous(): void
    {
        $constraint = new NewEmailAddress();

        $this->executionContextMock
            ->expects(self::once())
            ->method('buildViolation')
            ->with($constraint->message)
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects(self::once())
            ->method('atPath')
            ->with('newEmailAddress')
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects(self::once())
            ->method('addViolation')
            ->willReturn(null);

        $this->newEmailAddressValidator->initialize($this->executionContextMock);

        $dummyUser = new class() extends AbstractUser {
        };
        // current email is verified
        $dummyUser->setEmailAddressVerified(true);
        $dummyUser
            ->setEmailAddress('old@email.com')
            ->setNewEmailAddress('old@email.com');
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);

        $this->repositoryMock
            ->expects(self::once())
            ->method('findExistingUserByNewEmail')
            ->with($dummyUser)
            ->willReturn(null);

        $dummyUser->setEmailAddressVerified(false);
        $dummyUser
            ->setEmailAddress('old@email.com')
            ->setNewEmailAddress('old@email.com');
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }

    public function test_error_if_new_email_is_already_in_database(): void
    {
        $dummyUser = new class() extends AbstractUser {
        };

        $this->repositoryMock
            ->expects(self::once())
            ->method('findExistingUserByNewEmail')
            ->with($dummyUser)
            ->willReturn($dummyUser);

        $constraint = new NewEmailAddress();

        $this->executionContextMock
            ->expects(self::once())
            ->method('buildViolation')
            ->with($constraint->uniqueMessage)
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects(self::once())
            ->method('atPath')
            ->with('newEmailAddress')
            ->willReturn($this->constraintViolationBuilderMock);

        $this->constraintViolationBuilderMock
            ->expects(self::once())
            ->method('addViolation')
            ->willReturn(null);

        $this->newEmailAddressValidator->initialize($this->executionContextMock);
        $dummyUser
            ->setEmailAddress('old@email.com')
            ->setNewEmailAddress('new@email.com');
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }

    public function test_no_error_if_new_email_is_unique(): void
    {
        $dummyUser = new class() extends AbstractUser {
        };

        $this->repositoryMock
            ->expects(self::once())
            ->method('findExistingUserByNewEmail')
            ->with($dummyUser)
            ->willReturn(null);

        $constraint = new NewEmailAddress();

        $this->executionContextMock
            ->expects($this->never())
            ->method('buildViolation');

        $this->constraintViolationBuilderMock
            ->expects($this->never())
            ->method('addViolation');

        $this->newEmailAddressValidator->initialize($this->executionContextMock);
        $dummyUser
            ->setEmailAddress('old@email.com')
            ->setNewEmailAddress('new@email.com');
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }
}
