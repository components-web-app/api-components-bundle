<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\User;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\RequestLimitReachedException;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserDataProcessorTest extends TestCase
{
    private const PASSWORD_RESET_TTL = 600;
    private const NEW_EMAIL_TTL = 1200;
    private const EMAIL_VERIFICATION_TTL = 1800;

    /**
     * @return iterable<string, array{string, \Closure(AbstractUser, \DateTime): void, \Closure(AbstractUser): ?string, int}>
     */
    public static function flows(): iterable
    {
        yield 'password reset' => [
            'updatePasswordConfirmationToken',
            static fn (AbstractUser $user, \DateTime $at) => $user->setPasswordRequestedAt($at),
            static fn (AbstractUser $user): ?string => $user->getNewPasswordConfirmationToken(),
            self::PASSWORD_RESET_TTL,
        ];
        yield 'new email confirmation' => [
            'updateNewEmailToken',
            static fn (AbstractUser $user, \DateTime $at) => $user->setNewEmailAddressChangeRequestedAt($at),
            static fn (AbstractUser $user): ?string => $user->getNewEmailConfirmationToken(),
            self::NEW_EMAIL_TTL,
        ];
        yield 'email verification' => [
            'updateVerifyEmailToken',
            static fn (AbstractUser $user, \DateTime $at) => $user->setEmailAddressVerificationRequestedAt($at),
            static fn (AbstractUser $user): ?string => $user->getEmailAddressVerifyToken(),
            self::EMAIL_VERIFICATION_TTL,
        ];
    }

    #[DataProvider('flows')]
    public function test_a_request_inside_its_throttle_reports_the_seconds_remaining_and_changes_nothing(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $user = $this->createUser();
        $setRequestedAt($user, new \DateTime('-100 seconds'));

        try {
            $this->createProcessor($user)->{$method}('my_username');
            self::fail('The throttled request was not refused');
        } catch (RequestLimitReachedException $exception) {
            self::assertGreaterThanOrEqual($ttl - 101, $exception->getRetryAfter());
            self::assertLessThanOrEqual($ttl - 100, $exception->getRetryAfter());
        }
        self::assertNull($getToken($user));
    }

    #[DataProvider('flows')]
    public function test_a_request_after_its_throttle_issues_a_new_token(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $user = $this->createUser();
        $setRequestedAt($user, new \DateTime(\sprintf('-%d seconds', $ttl)));

        self::assertSame($user, $this->createProcessor($user)->{$method}('my_username'));
        self::assertSame('hashed', $getToken($user));
    }

    #[DataProvider('flows')]
    public function test_a_first_request_issues_a_new_token(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $user = $this->createUser();

        self::assertSame($user, $this->createProcessor($user)->{$method}('my_username'));
        self::assertSame('hashed', $getToken($user));
    }

    #[DataProvider('flows')]
    public function test_each_flow_is_throttled_only_by_its_own_request_time(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $user = $this->createUser();
        $user->setPasswordRequestedAt(new \DateTime());
        $user->setNewEmailAddressChangeRequestedAt(new \DateTime());
        $user->setEmailAddressVerificationRequestedAt(new \DateTime());
        $setters = [
            'updatePasswordConfirmationToken' => static fn () => $user->setPasswordRequestedAt(null),
            'updateNewEmailToken' => static fn () => $user->setNewEmailAddressChangeRequestedAt(null),
            'updateVerifyEmailToken' => static fn () => $user->setEmailAddressVerificationRequestedAt(null),
        ];
        $setters[$method]();

        self::assertSame($user, $this->createProcessor($user)->{$method}('my_username'));
    }

    #[DataProvider('flows')]
    public function test_the_default_throttles(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $user = $this->createUser();
        $setRequestedAt($user, new \DateTime());
        $processor = new UserDataProcessor(
            $this->createHasher(),
            $this->createRepository($user),
            $this->createStub(PasswordHasherFactoryInterface::class),
            false,
            false,
            false,
        );

        try {
            $processor->{$method}('my_username');
            self::fail('The throttled request was not refused');
        } catch (RequestLimitReachedException $exception) {
            $expected = 'updatePasswordConfirmationToken' === $method ? 86400 : 300;
            self::assertGreaterThanOrEqual($expected - 1, $exception->getRetryAfter());
            self::assertLessThanOrEqual($expected, $exception->getRetryAfter());
        }
    }

    #[DataProvider('flows')]
    public function test_an_unknown_user_is_not_found(string $method, \Closure $setRequestedAt, \Closure $getToken, int $ttl): void
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('loadUserByIdentifier')->willReturn(null);
        $processor = new UserDataProcessor($this->createHasher(), $repository, $this->createStub(PasswordHasherFactoryInterface::class), false, false, false);

        $this->expectException(InvalidArgumentException::class);
        $processor->{$method}('no_user');
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setUsername('my_username');

        return $user;
    }

    private function createProcessor(User $user): UserDataProcessor
    {
        return new UserDataProcessor(
            $this->createHasher(),
            $this->createRepository($user),
            $this->createStub(PasswordHasherFactoryInterface::class),
            false,
            false,
            false,
            passwordResetRepeatTtl: self::PASSWORD_RESET_TTL,
            newEmailConfirmationRepeatTtl: self::NEW_EMAIL_TTL,
            emailVerificationRepeatTtl: self::EMAIL_VERIFICATION_TTL,
        );
    }

    private function createHasher(): UserPasswordHasherInterface
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        return $hasher;
    }

    private function createRepository(User $user): UserRepositoryInterface
    {
        $repository = $this->createStub(UserRepositoryInterface::class);
        $repository->method('loadUserByIdentifier')->willReturn($user);

        return $repository;
    }
}
