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
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddress;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

#[AllowMockObjectsWithoutExpectations]
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
        $constraint = new class extends Constraint {
        };
        $dummyUser = new class {
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
        $dummyUser = new class extends AbstractUser {
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
            ->method('addViolation');

        $this->newEmailAddressValidator->initialize($this->executionContextMock);

        $dummyUser = new class extends AbstractUser {
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
        $dummyUser = new class extends AbstractUser {
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
            ->method('addViolation');

        $this->newEmailAddressValidator->initialize($this->executionContextMock);
        $dummyUser
            ->setEmailAddress('old@email.com')
            ->setNewEmailAddress('new@email.com');
        $this->newEmailAddressValidator->validate($dummyUser, $constraint);
    }

    public function test_no_error_if_new_email_is_unique(): void
    {
        $dummyUser = new class extends AbstractUser {
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

    // --- Deterministic single-branch coverage (kills surviving mutants) ---

    /**
     * Captures the messages and atPath targets of every violation the validator raises for a
     * single validate() call, without relying on ordered mock expectations across branches.
     *
     * @return array{messages: list<string>, paths: list<string>}
     */
    private function captureViolations(NewEmailAddressValidator $validator, AbstractUser $user, NewEmailAddress $constraint): array
    {
        $messages = [];
        $paths = [];

        $builder = $this->createStub(ConstraintViolationBuilderInterface::class);
        $builder->method('atPath')->willReturnCallback(static function (string $path) use (&$paths, $builder): ConstraintViolationBuilderInterface {
            $paths[] = $path;

            return $builder;
        });

        $context = $this->createStub(ExecutionContextInterface::class);
        $context->method('buildViolation')->willReturnCallback(static function (string $message) use (&$messages, $builder): ConstraintViolationBuilderInterface {
            $messages[] = $message;

            return $builder;
        });

        $validator->initialize($context);
        $validator->validate($user, $constraint);

        return ['messages' => $messages, 'paths' => $paths];
    }

    /**
     * @param AbstractUser|null $repoResult what the repository returns for findExistingUserByNewEmail
     */
    private function makeValidator(?AbstractUser $repoResult): NewEmailAddressValidator
    {
        $repo = $this->createStub(UserRepository::class);
        $repo->method('findExistingUserByNewEmail')->willReturn($repoResult);

        return new NewEmailAddressValidator($repo);
    }

    public function test_empty_new_email_returns_before_match_check(): void
    {
        // Kills LogicalNot (line 44) and ReturnRemoval (line 45): with an empty new email that equals
        // the (empty) current address and a verified state, only the early return prevents a spurious
        // "same as previous" violation. The mutant reaches line 48 ('' === '') and raises `message`.
        $validator = $this->makeValidator(null);

        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerified(true);
        $user->setEmailAddress('')->setNewEmailAddress('');

        $result = $this->captureViolations($validator, $user, new NewEmailAddress());

        self::assertSame([], $result['messages']);
    }

    public function test_verified_matching_email_adds_only_the_match_message(): void
    {
        // Kills Identical (=== → !==), MethodCallRemoval (line 49) and ReturnRemoval (line 53). The
        // repository is primed to return a user, so if the code failed to return after the match it
        // would add a SECOND (uniqueMessage) violation — the exact-array assertion catches that.
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerified(true);
        $user->setEmailAddress('same@example.com')->setNewEmailAddress('same@example.com');

        $validator = $this->makeValidator($user);

        $constraint = new NewEmailAddress();
        $result = $this->captureViolations($validator, $user, $constraint);

        self::assertSame([$constraint->message], $result['messages']);
        self::assertSame(['newEmailAddress'], $result['paths']);
    }

    public function test_verified_but_different_email_raises_no_match_violation(): void
    {
        // Kills the second-operand negation and LogicalAndNegation on line 48: a DIFFERENT new email
        // must not trigger the match branch, and the repository (primed null) adds nothing.
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerified(true);
        $user->setEmailAddress('current@example.com')->setNewEmailAddress('changed@example.com');

        $validator = $this->makeValidator(null);

        $result = $this->captureViolations($validator, $user, new NewEmailAddress());

        self::assertSame([], $result['messages']);
    }

    public function test_unverified_matching_email_raises_no_match_violation(): void
    {
        // Kills LogicalAnd (&& → ||) and the first-operand negation on line 48: when the address is
        // NOT verified, an identical new email must not trigger the match violation.
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerified(false);
        $user->setEmailAddress('same@example.com')->setNewEmailAddress('same@example.com');

        $validator = $this->makeValidator(null);

        $result = $this->captureViolations($validator, $user, new NewEmailAddress());

        self::assertSame([], $result['messages']);
    }

    public function test_existing_user_with_new_email_raises_unique_message(): void
    {
        // Kills IfNegation (line 56) and MethodCallRemoval (line 57): when the repository finds an
        // existing user for the new email, exactly `uniqueMessage` must fire on `newEmailAddress`.
        $user = new class extends AbstractUser {
        };
        $user->setEmailAddressVerified(false);
        $user->setEmailAddress('current@example.com')->setNewEmailAddress('taken@example.com');

        $validator = $this->makeValidator($user);

        $constraint = new NewEmailAddress();
        $result = $this->captureViolations($validator, $user, $constraint);

        self::assertSame([$constraint->uniqueMessage], $result['messages']);
        self::assertSame(['newEmailAddress'], $result['paths']);
    }
}
