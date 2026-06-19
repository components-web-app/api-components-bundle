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
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserFactoryTest extends TestCase
{
    private function buildFactory(?AbstractUser &$captured = null): UserFactory
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->willReturnCallback(static function ($user) use (&$captured) {
            $captured = $user;
        });
        $em->expects(self::once())->method('flush');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $userClass = new class extends AbstractUser {
        };

        return new UserFactory(
            $em,
            $this->createStub(ValidatorInterface::class),
            $this->createStub(UserRepositoryInterface::class),
            $this->createStub(TimestampedDataPersister::class),
            $hasher,
            $userClass::class
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
}
