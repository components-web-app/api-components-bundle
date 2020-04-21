<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Mailer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Exception\MailerTransportException;
use Silverback\ApiComponentBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class UserMailerTest extends TestCase
{
    /**
     * @var MockObject|MailerInterface
     */
    private MockObject $mailerMock;
    /**
     * @var MockObject|RefererUrlHelper
     */
    private MockObject $refererUrlHelperMock;
    /**
     * @var MockObject|RequestStack
     */
    private MockObject $requestStackMock;

    protected function setUp(): void
    {
        $this->mailerMock = $this->createMock(MailerInterface::class);
        $this->refererUrlHelperMock = $this->createMock(RefererUrlHelper::class);
        $this->requestStackMock = $this->createMock(RequestStack::class);
    }

    private function getUserMailer(array $context = []): UserMailer
    {
        return new UserMailer($this->mailerMock, $this->refererUrlHelperMock, $this->requestStackMock, $context);
    }

    public function test_error_if_no_token_for_password_reset_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A new password confirmation token must be set to send the `password reset` email');
        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_error_if_no_username_for_password_reset_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setNewPasswordConfirmationToken('password_token');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have a username set to send them any email');

        $this->pathToRefererUrlMethodNotCalled();

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_error_if_no_email_for_password_reset_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user
            ->setNewPasswordConfirmationToken('password_token')
            ->setUsername('username');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have an email address set to send them any email');

        $this->pathToRefererUrlMethodCalled('/reset-password/username/password_token');

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_catch_invalid_email_address(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setNewPasswordConfirmationToken('password_token');
        $user->setUsername('username')->setEmailAddress('invalid');

        $this->pathToRefererUrlMethodCalled('/reset-password/username/password_token');

        $this->mailerMock
            ->expects($this->never())
            ->method('send');

        $this->expectException(RfcComplianceException::class);
        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_catch_transport_exception(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setNewPasswordConfirmationToken('password_token');
        $user->setUsername('username')->setEmailAddress('valid@email.com');

        $this->pathToRefererUrlMethodCalled('/reset-password/username/password_token');

        $email = (new TemplatedEmail())
            ->to(Address::fromString('valid@email.com'))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_forgot_password.html.twig')
            ->context([
                'reset_url' => 'https://referer.com/path',
                'user' => $user,
                'username' => 'username',
                'website_name' => 'Website Name',
            ]);

        $expectedCatchException = new TransportException('exception message');
        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($email)
            ->willThrowException($expectedCatchException);

        $expectedExceptionThrown = new MailerTransportException('exception message');
        $this->expectExceptionObject($expectedExceptionThrown);

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_valid_password_reset_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setNewPasswordConfirmationToken('password_token');
        $user->setUsername('username')->setEmailAddress('email@address.com');

        $this->pathToRefererUrlMethodCalled('/reset-password/username/password_token');

        $email = (new TemplatedEmail())
            ->to(Address::fromString('email@address.com'))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_forgot_password.html.twig')
            ->context([
                'reset_url' => 'https://referer.com/path',
                'user' => $user,
                'username' => 'username',
                'website_name' => 'Website Name',
            ]);

        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($email);

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_valid_password_reset_email_with_custom_website_name_and_reset_path(): void
    {
        $userMailer = $this->getUserMailer(['website_name' => 'My Website', 'paths' => ['/custom-reset-path']);
        $user = new class() extends AbstractUser {
        };
        $user->setNewPasswordConfirmationToken('password_token');
        $user->setUsername('username')->setEmailAddress('email@address.com');

        $this->pathToRefererUrlMethodCalled('/custom-reset-path');

        $email = (new TemplatedEmail())
            ->to(Address::fromString('email@address.com'))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_forgot_password.html.twig')
            ->context([
                'reset_url' => 'https://referer.com/path',
                'user' => $user,
                'username' => 'username',
                'website_name' => 'My Website',
            ]);

        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($email);

        $userMailer->sendPasswordResetEmail($user);
    }

    public function test_error_if_no_username_for_verify_change_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The user must have a username set to send them any email');
        $userMailer->sendChangeEmailVerificationEmail($user);
    }

    public function test_error_if_no_token_for_verify_change_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setUsername('username');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A new email verification token must be set to send the `email verification` email');
        $userMailer->sendChangeEmailVerificationEmail($user);
    }

    public function test_change_email_confirmation_email(): void
    {
        $userMailer = $this->getUserMailer();
        $user = new class() extends AbstractUser {
        };
        $user->setNewEmailVerificationToken('email_token');
        $user->setUsername('change_email_username')->setEmailAddress('user@email.com');

        $this->pathToRefererUrlMethodCalled('/verify-new-email/change_email_username/email_token');

        $email = (new TemplatedEmail())
            ->to(Address::fromString('user@email.com'))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_verify_email.html.twig')
            ->context([
                'verify_url' => 'https://referer.com/path',
                'user' => $user,
                'username' => 'change_email_username',
                'website_name' => 'Website Name',
            ]);

        $this->mailerMock
            ->expects($this->once())
            ->method('send')
            ->with($email);

        $userMailer->sendChangeEmailVerificationEmail($user);
    }

    public function test_send_user_welcome_email_disabled(): void
    {
        $userMailer = $this->getUserMailer('Website', '/pw-path', '/verify-new-email/{{ username }}/{{ token }}', false);

    }

    private function pathToRefererUrlMethodNotCalled(): void
    {
        $this->requestStackMock
            ->expects($this->never())
            ->method('getMasterRequest');

        $this->refererUrlHelperMock
            ->expects($this->never())
            ->method('getAbsoluteUrl');
    }

    private function pathToRefererUrlMethodCalled($defaultPath): void
    {
        $request = new Request();

        $this->requestStackMock
            ->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn($request);

        $this->refererUrlHelperMock
            ->expects($this->once())
            ->method('getAbsoluteUrl')
            ->with($defaultPath)
            ->willReturn('https://referer.com/path');
    }
}
