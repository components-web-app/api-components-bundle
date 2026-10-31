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

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddress;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressRequiresPassword;
use Silverback\ApiComponentsBundle\Validator\Constraints\NewEmailAddressRequiresPasswordValidator;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

class NewEmailAddressRequiresPasswordValidatorTest extends ConstraintValidatorTestCase
{
    private ?ObjectManager $manager = null;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ?ObjectManager => User::class === $class ? $this->manager : null);

        return new NewEmailAddressRequiresPasswordValidator($registry);
    }

    public function test_a_new_email_address_that_differs_from_the_stored_one_validates_the_current_password_in_the_constraints_groups(): void
    {
        $user = $this->user('new@example.com', 'my-password');
        $this->storeOriginalData($user, ['newEmailAddress' => 'old-pending@example.com']);
        $constraint = new NewEmailAddressRequiresPassword(groups: ['User:emailAddress', 'Default']);

        $this->expectValidateValueAt(0, 'oldPassword', 'my-password', new UserPassword(message: 'You have not entered your current password correctly. Please try again.', groups: ['User:emailAddress', 'Default']));

        $this->validator->validate($user, $constraint);

        $this->assertNoViolation();
    }

    public function test_the_constraints_message_is_the_password_violations_message(): void
    {
        $user = $this->user('new@example.com', null);
        $this->storeOriginalData($user, ['newEmailAddress' => null]);
        $constraint = new NewEmailAddressRequiresPassword();
        $constraint->message = 'Enter your password.';

        $this->expectValidateValueAt(0, 'oldPassword', null, new UserPassword(message: 'Enter your password.', groups: ['Default']));

        $this->validator->validate($user, $constraint);
    }

    public function test_a_user_with_no_original_data_validates_the_current_password(): void
    {
        $user = $this->user('new@example.com', 'my-password');
        $this->storeOriginalData($user, []);

        $this->expectValidateValueAt(0, 'oldPassword', 'my-password', new UserPassword(message: 'You have not entered your current password correctly. Please try again.', groups: ['Default']));

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());
    }

    public function test_a_user_with_no_orm_manager_validates_the_current_password(): void
    {
        $user = $this->user('new@example.com', 'my-password');
        $this->manager = $this->createStub(ObjectManager::class);

        $this->expectValidateValueAt(0, 'oldPassword', 'my-password', new UserPassword(message: 'You have not entered your current password correctly. Please try again.', groups: ['Default']));

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());
    }

    public function test_a_stored_new_email_address_that_is_not_a_string_counts_as_none(): void
    {
        $user = $this->user('new@example.com', 'my-password');
        $this->storeOriginalData($user, ['newEmailAddress' => 5]);

        $this->expectValidateValueAt(0, 'oldPassword', 'my-password', new UserPassword(message: 'You have not entered your current password correctly. Please try again.', groups: ['Default']));

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());
    }

    public function test_an_unchanged_new_email_address_needs_no_password(): void
    {
        $user = $this->user('new@example.com', null);
        $this->storeOriginalData($user, ['newEmailAddress' => 'new@example.com']);

        $this->expectNoValidate();

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());

        $this->assertNoViolation();
    }

    public function test_cancelling_with_null_needs_no_password(): void
    {
        $user = $this->user(null, null);
        $this->storeOriginalData($user, ['newEmailAddress' => 'new@example.com']);

        $this->expectNoValidate();

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());

        $this->assertNoViolation();
    }

    public function test_cancelling_with_a_blank_string_needs_no_password(): void
    {
        $user = $this->user('', null);
        $this->storeOriginalData($user, ['newEmailAddress' => 'new@example.com']);

        $this->expectNoValidate();

        $this->validator->validate($user, new NewEmailAddressRequiresPassword());

        $this->assertNoViolation();
    }

    public function test_another_constraint_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate($this->user('new@example.com', null), new NewEmailAddress());
    }

    public function test_a_value_that_is_not_a_user_is_refused(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate(new \stdClass(), new NewEmailAddressRequiresPassword());
    }

    private function user(?string $newEmailAddress, ?string $oldPassword): User
    {
        $user = new User();
        $user->setNewEmailAddress($newEmailAddress)->setOldPassword($oldPassword);

        return $user;
    }

    /**
     * @param array<string, mixed> $originalData
     */
    private function storeOriginalData(User $user, array $originalData): void
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(static fn (object $entity): array => $entity === $user ? $originalData : ['newEmailAddress' => 'new@example.com']);
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $this->manager = $manager;
    }
}
