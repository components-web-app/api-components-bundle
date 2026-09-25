<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Api;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\Helper\User\UserMailer;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

class UserEventListenerTest extends TestCase
{
    public function test_an_email_verification_after_a_write_uses_the_after_write_path(): void
    {
        $previousUser = new User();
        $user = clone $previousUser;
        $user->setEmailAddressVerifyToken('hashed');
        $user->plainEmailAddressVerifyToken = 'plain';

        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::once())->method('sendEmailVerifyEmailAfterWrite')->with($user);
        $mailer->expects(self::never())->method('sendEmailVerifyEmail');

        (new UserEventListener($mailer, $this->createStub(Security::class)))->postWrite($user, $previousUser);
    }

    public function test_an_email_change_confirmation_after_a_write_uses_the_after_write_path(): void
    {
        $previousUser = new User();
        $user = clone $previousUser;
        $user->setNewEmailConfirmationToken('hashed');

        $mailer = $this->createMock(UserMailer::class);
        $mailer->expects(self::once())->method('sendChangeEmailConfirmationEmailAfterWrite')->with($user);
        $mailer->expects(self::never())->method('sendChangeEmailConfirmationEmail');

        (new UserEventListener($mailer, $this->createStub(Security::class)))->postWrite($user, $previousUser);
    }
}
