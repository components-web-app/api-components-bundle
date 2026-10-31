<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\UserPasswordStateProcessor;
use Silverback\ApiComponentsBundle\Helper\User\UserDataProcessor;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserPasswordStateProcessorTest extends TestCase
{
    public function test_a_users_plain_password_is_hashed_before_the_write(): void
    {
        $user = $this->userWithNewPassword();
        $operation = new Patch();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')
            ->with(self::callback(static fn (User $written): bool => 'hashed' === $written->getPassword() && null === $written->getPlainPassword()), $operation, ['id' => 1], ['previous_data' => null])
            ->willReturn('written');

        self::assertSame('written', $this->processor($inner)->process($user, $operation, ['id' => 1], ['previous_data' => null]));
    }

    public function test_a_user_written_through_an_operation_that_is_not_http_is_not_hashed(): void
    {
        $user = $this->userWithNewPassword();

        self::assertSame('written', $this->processor($this->inner())->process($user, new Mutation()));
        self::assertSame('old', $user->getPassword());
        self::assertSame('new password', $user->getPlainPassword());
    }

    public function test_data_other_than_a_user_is_passed_to_the_write(): void
    {
        $data = new \stdClass();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($data)->willReturn('written');

        self::assertSame('written', $this->processor($inner)->process($data, new Post()));
    }

    private function userWithNewPassword(): User
    {
        $user = new User('daniel', 'daniel@example.com');
        $user->setPassword('old')->setPlainPassword('new password');

        return $user;
    }

    private function processor(ProcessorInterface $inner): UserPasswordStateProcessor
    {
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        return new UserPasswordStateProcessor($inner, new UserDataProcessor($hasher, $this->createStub(UserRepositoryInterface::class), $this->createStub(PasswordHasherFactoryInterface::class), false, false, false));
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
