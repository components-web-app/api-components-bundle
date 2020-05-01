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

namespace Silverback\ApiComponentsBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Security\UserChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\UnsupportedUser;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Component\Security\Core\Exception\DisabledException;

class UserCheckerTest extends TestCase
{
    public function test_check_post_auth_does_nothing_and_returns_nothing(): void
    {
        $userChecker = new UserChecker();
        $user = new User();
        $this->assertNull($userChecker->checkPostAuth($user));
    }

    public function test_pre_auth_does_nothing_if_object_is_not_user(): void
    {
        $userChecker = new UserChecker();
        $this->assertNull($userChecker->checkPreAuth(new UnsupportedUser()));
    }

    public function test_user_not_enabled_throws_exception(): void
    {
        $userChecker = new UserChecker();
        $user = new class() extends AbstractUser {
        };

        $user->setEnabled(false)->setEmailAddressVerified(true);
        $this->expectException(DisabledException::class);
        $userChecker->checkPreAuth($user);
    }

    public function test_user_with_unverified_email_throws_exception(): void
    {
        $userChecker = new UserChecker();
        $user = new class() extends AbstractUser {
        };

        $user->setEnabled(true);
        $user->setEmailAddressVerified(false);
        $this->expectException(DisabledException::class);
        $userChecker->checkPreAuth($user);
    }

    public function test_user_with_unverified_email_can_be_allowed_and_not_throw_exception(): void
    {
        $userChecker = new UserChecker(false);
        $user = new class() extends AbstractUser {
        };

        $user->setEnabled(true);
        $user->setEmailAddressVerified(false);
        $this->assertNull($userChecker->checkPreAuth($user));
    }
}
