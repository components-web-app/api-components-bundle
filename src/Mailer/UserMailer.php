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

namespace Silverback\ApiComponentBundle\Mailer;

use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserMailer
{
    private MailerInterface $mailer;
    private string $websiteName;
    private bool $sendUserWelcomeEmailEnabled;
    private bool $sendUserEnabledEmailEnabled;
    private bool $sendUserUsernameChangedEmailEnabled;
    private bool $sendUserPasswordChangedEmailEnabled;

    public function __construct(
        MailerInterface $mailer,
        string $websiteName,
        bool $sendUserWelcomeEmailEnabled = true,
        bool $sendUserEnabledEmailEnabled = true,
        bool $sendUserUsernameChangedEmailEnabled = true,
        bool $sendUserPasswordChangedEmailEnabled = true)
    {
        $this->mailer = $mailer;
        $this->websiteName = $websiteName;
        $this->sendUserWelcomeEmailEnabled = $sendUserWelcomeEmailEnabled;
        $this->sendUserEnabledEmailEnabled = $sendUserEnabledEmailEnabled;
        $this->sendUserUsernameChangedEmailEnabled = $sendUserUsernameChangedEmailEnabled;
        $this->sendUserPasswordChangedEmailEnabled = $sendUserPasswordChangedEmailEnabled;
    }

    public function sendPasswordResetEmail(AbstractUser $user, string $resetUrl): void
    {
        $userEmail = $this->getUserEmail($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/forgot_password.html.twig')
            ->context([
                'user' => $user,
                'reset_url' => $resetUrl,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): void
    {
    }

    public function sendUserWelcomeEmail(AbstractUser $user): void
    {
        if (!$this->sendUserWelcomeEmailEnabled) {
            return;
        }
        $userEmail = $this->getUserEmail($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject(sprintf('Welcome to %s', $this->websiteName))
            ->htmlTemplate('@SilverbackApiComponent/emails/user_welcome.html.twig')
            ->context([
                'user' => $user,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendUserEnabledEmail(AbstractUser $user): void
    {
        if (!$this->sendUserEnabledEmailEnabled) {
            return;
        }
        $userEmail = $this->getUserEmail($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your account has been enabled')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_enabled.html.twig')
            ->context([
                'user' => $user,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendUsernameChangedEmail(AbstractUser $user): void
    {
        if (!$this->sendUserUsernameChangedEmailEnabled) {
            return;
        }
        $userEmail = $this->getUserEmail($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your username has been changed')
            ->htmlTemplate('@SilverbackApiComponent/emails/username_changed.html.twig')
            ->context([
                'user' => $user,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendPasswordChangedEmail(AbstractUser $user): void
    {
        if (!$this->sendUserPasswordChangedEmailEnabled) {
            return;
        }
        $userEmail = $this->getUserEmail($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your password has been changed')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_password_changed.html.twig')
            ->context([
                'user' => $user,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    private function getUserEmail(AbstractUser $user): string
    {
        if (!($userEmail = $user->getEmailAddress())) {
            throw new InvalidParameterException('The user must have an email address to send a password reset email');
        }

        return $userEmail;
    }
}
