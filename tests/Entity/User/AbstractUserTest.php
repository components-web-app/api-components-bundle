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

namespace Silverback\ApiComponentsBundle\Tests\Entity\User;

use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;

class AbstractUserTest extends TestCase
{
    public function test_construct(): void
    {
        $user = new class('username', 'email@address.com', true, ['ROLE_ADMIN'], 'password', false) extends AbstractUser {
        };
        $this->assertEquals('username', $user->getUsername());
        $this->assertEquals('email@address.com', $user->getEmailAddress());
        $this->assertTrue($user->isEmailAddressVerified());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
        $this->assertEquals('password', $user->getPassword());
        $this->assertFalse($user->isEnabled());

        $user = new class() extends AbstractUser {
        };
        $this->assertEquals('', $user->getUsername());
        $this->assertEquals('', $user->getEmailAddress());
        $this->assertFalse($user->isEmailAddressVerified());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
        $this->assertEquals('', $user->getPassword());
        $this->assertTrue($user->isEnabled());
    }

    public function test_getters_and_setters(): void
    {
        $user = new class() extends AbstractUser {
        };

        $this->assertEquals($user, $user->setUsername('username'));
        $this->assertEquals('username', $user->getUsername());

        $this->assertEquals($user, $user->setEmailAddress('email'));
        $this->assertEquals('email', $user->getEmailAddress());

        $this->assertEquals($user, $user->setRoles([]));
        $this->assertEquals([], $user->getRoles());

        $this->assertEquals($user, $user->setEnabled(false));
        $this->assertFalse($user->isEnabled());

        $this->assertEquals($user, $user->setPassword('password123'));
        $this->assertEquals('password123', $user->getPassword());

        $this->assertEquals($user, $user->setPlainPassword('password123'));
        $this->assertEquals('password123', $user->getPlainPassword());

        $this->assertEquals($user, $user->setNewPasswordConfirmationToken('pwtoken'));
        $this->assertEquals('pwtoken', $user->getNewPasswordConfirmationToken());

        $this->assertEquals($user, $user->setPasswordRequestedAt($dateTime = new \DateTime()));
        $this->assertEquals($dateTime, $user->getPasswordRequestedAt());

        $this->assertEquals($user, $user->setPasswordRequestedAt(null));
        $this->assertNull($user->getPasswordRequestedAt());

        $this->assertEquals($user, $user->setOldPassword('old_pw'));
        $this->assertEquals('old_pw', $user->getOldPassword());

        $this->assertEquals($user, $user->setNewEmailAddress('new@email'));
        $this->assertEquals('new@email', $user->getNewEmailAddress());

        $this->assertEquals($user, $user->setNewEmailConfirmationToken('emtoken'));
        $this->assertEquals('emtoken', $user->getNewEmailConfirmationToken());

        $this->assertEquals($user, $user->setEmailAddressVerified(true));
        $this->assertTrue($user->isEmailAddressVerified());

        $this->assertEquals($user, $user->setEmailAddressVerified(true));
        $this->assertTrue($user->isEmailAddressVerified());

        $user->setPlainPassword('plain_password');
        $user->eraseCredentials();
        $this->assertNull($user->getPlainPassword());
    }

    public function test_is_password_request_limit_reached(): void
    {
        $user = new class() extends AbstractUser {
        };
        $dateTime = new \DateTime();
        $user->setPasswordRequestedAt($dateTime);
        $this->assertFalse($user->isPasswordRequestLimitReached(0));

        $dateTime->modify('-1 seconds');
        $user->setPasswordRequestedAt($dateTime);

        $this->assertTrue($user->isPasswordRequestLimitReached(2));
        $this->assertFalse($user->isPasswordRequestLimitReached(1));
    }

    public function test_user_serialization(): void
    {
        $user = new class('username', 'email@address', true, ['ROLE_USER']) extends AbstractUser {
        };
        $user->setPassword('password_encoded');
        $original = [
            '253e0f90-8842-4731-91dd-0191816e6a28',
            'username',
            'email@address',
            'password_encoded',
            true,
            ['ROLE_USER'],
        ];
        $serialized = serialize($original);
        $this->assertEquals($user, $user->unserialize($serialized));
        $this->assertEquals($serialized, $user->serialize());

        $user->unserialize(
            serialize(
                [
                    $newId = '253e0f90-8842-4731-91dd-0191816e6a28',
                    'new_user',
                    'new@email',
                    'new_pass',
                    false,
                    ['ROLE_ADMIN'],
                ]
            )
        );

        $this->assertEquals(Uuid::fromString($newId), $user->getId());
        $this->assertEquals('new_user', $user->getUsername());
        $this->assertEquals('new@email', $user->getEmailAddress());
        $this->assertEquals('new_pass', $user->getPassword());
        $this->assertFalse($user->isEnabled());
        $this->assertEquals(['ROLE_ADMIN'], $user->getRoles());
    }

    public function test_unserialize_object_throws_exception(): void
    {
        $user = new class() extends AbstractUser {
        };
        $original = [
            '253e0f90-8842-4731-91dd-0191816e6a28',
            'new_user',
            'new@email',
            'new_pass',
            false,
            [new User()],
        ];
        $serialized = serialize($original);
        $this->assertEquals($user, $user->unserialize($serialized));
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $user->getRoles()[0]);

        $this->assertEquals($user->getId(), (string) $user);
    }
}
