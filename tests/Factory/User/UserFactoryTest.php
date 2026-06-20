<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Factory\User;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Factory\User\UserFactory;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserFactoryTest extends TestCase
{
    private string $userClass;

    protected function setUp(): void
    {
        $userClass = new class extends AbstractUser {};
        $this->userClass = $userClass::class;
    }

    private function buildFactory(
        ?AbstractUser &$captured = null,
        ?TimestampedDataPersister $timestampedPersister = null,
        ?ValidatorInterface $validator = null,
        ?UserRepositoryInterface $userRepository = null,
    ): UserFactory {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(static function ($user) use (&$captured) {
            $captured = $user;
        });
        $em->expects(self::once())->method('flush');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        return new UserFactory(
            $em,
            $validator ?? $this->createStub(ValidatorInterface::class),
            $userRepository ?? $this->createStub(UserRepositoryInterface::class),
            $timestampedPersister ?? $this->createStub(TimestampedDataPersister::class),
            $hasher,
            $this->userClass,
        );
    }

    public function test_default_user_gets_role_user_only(): void
    {
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com');

        self::assertSame(['ROLE_USER'], $captured->getRoles());
    }

    public function test_admin_flag_grants_role_admin_not_super_admin(): void
    {
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com', false, false, true);

        self::assertSame(['ROLE_ADMIN'], $captured->getRoles());
        self::assertNotContains('ROLE_SUPER_ADMIN', $captured->getRoles());
    }

    public function test_super_admin_flag_grants_role_super_admin(): void
    {
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com', false, true, false);

        self::assertSame(['ROLE_SUPER_ADMIN'], $captured->getRoles());
    }

    public function test_null_email_falls_back_to_username(): void
    {
        // Mutant 59: if(!$email) → if($email) — email fallback to username inverted
        $this->buildFactory($captured)->create('bob@example.com', 'pass', null);

        self::assertSame('bob@example.com', $captured->getEmailAddress());
    }

    public function test_explicit_email_is_used_not_username(): void
    {
        // Complement to above — verifies the email is used when provided, not the username
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com');

        self::assertSame('bob@example.com', $captured->getEmailAddress());
        self::assertNotSame('bob', $captured->getEmailAddress());
    }

    public function test_inactive_flag_disables_user(): void
    {
        // Mutant 61: setEnabled(!$inactive) → setEnabled($inactive) — enabled is inverted
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com', true);

        self::assertFalse($captured->isEnabled(), 'Inactive=true must produce an enabled=false user');
    }

    public function test_active_flag_keeps_user_enabled(): void
    {
        // Complement to inactive test
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com', false);

        self::assertTrue($captured->isEnabled(), 'Inactive=false must produce an enabled=true user');
    }

    public function test_email_address_is_verified_after_create(): void
    {
        // Mutant 62: setEmailAddressVerified(true) → false — email address should always be verified
        $this->buildFactory($captured)->create('bob', 'pass', 'bob@example.com');

        self::assertTrue($captured->isEmailAddressVerified(), 'Email address must be verified=true after factory create');
    }

    public function test_timestamped_persister_called_with_is_new_true(): void
    {
        // Mutant 63: persistTimestampedFields($user, true) → false
        // Mutant 64: call removed
        $timestampedPersister = $this->createMock(TimestampedDataPersister::class);
        $timestampedPersister->expects(self::once())
            ->method('persistTimestampedFields')
            ->with($this->isInstanceOf(AbstractUser::class), true);

        $this->buildFactory(timestampedPersister: $timestampedPersister)->create('bob', 'pass', 'bob@example.com');
    }

    public function test_validator_is_called(): void
    {
        // Mutant 65: validator->validate() call removed
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects(self::once())
            ->method('validate')
            ->with($this->isInstanceOf(AbstractUser::class))
            ->willReturn(new ConstraintViolationList());

        $this->buildFactory(validator: $validator)->create('bob', 'pass', 'bob@example.com');
    }

    public function test_overwrite_true_loads_existing_user_by_identifier(): void
    {
        // Mutant 60: Ternary swap — overwrite? repo->load : null → overwrite? null : repo->load
        $existingUser = new class extends AbstractUser {};
        $existingUser->setUsername('bob');

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())
            ->method('loadUserByIdentifier')
            ->with('bob')
            ->willReturn($existingUser);

        $captured = null;
        $this->buildFactory($captured, userRepository: $userRepository)->create('bob', 'pass', 'bob@example.com', false, false, false, true);

        // When overwrite=true, the existing user object must be used (not a new one)
        self::assertSame($existingUser, $captured);
    }

    public function test_overwrite_false_does_not_load_existing_user(): void
    {
        // Complement: overwrite=false must NOT call loadUserByIdentifier
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::never())->method('loadUserByIdentifier');

        $captured = null;
        $this->buildFactory($captured, userRepository: $userRepository)->create('bob', 'pass', 'bob@example.com', false, false, false, false);

        // A new user must be created (not the existing one)
        self::assertInstanceOf(AbstractUser::class, $captured);
    }
}
