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
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentBundle\Exception\MailerTransportException;
use Silverback\ApiComponentBundle\Exception\RfcComplianceException;
use Silverback\ApiComponentBundle\Url\RefererUrlHelper;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserMailer
{
    private MailerInterface $mailer;
    private RefererUrlHelper $refererUrlHelper;
    private RequestStack $requestStack;
    private string $websiteName;
    private string $defaultPasswordResetPath;
    private string $defaultChangeEmailVerifyPath;
    private bool $sendUserWelcomeEmailEnabled;
    private bool $sendUserEnabledEmailEnabled;
    private bool $sendUserUsernameChangedEmailEnabled;
    private bool $sendUserPasswordChangedEmailEnabled;

    public function __construct(
        MailerInterface $mailer,
        RefererUrlHelper $refererUrlHelper,
        RequestStack $requestStack,
        string $websiteName = 'Website Name',
        string $defaultPasswordResetPath = '/reset-password/{{ username }}/{{ token }}',
        string $defaultChangeEmailVerifyPath = '/verify-new-email/{{ username }}/{{ token }}',
        bool $sendUserWelcomeEmailEnabled = true,
        bool $sendUserEnabledEmailEnabled = true,
        bool $sendUserUsernameChangedEmailEnabled = true,
        bool $sendUserPasswordChangedEmailEnabled = true
    ) {
        $this->mailer = $mailer;
        $this->refererUrlHelper = $refererUrlHelper;
        $this->requestStack = $requestStack;
        $this->defaultPasswordResetPath = $defaultPasswordResetPath;
        $this->defaultChangeEmailVerifyPath = $defaultChangeEmailVerifyPath;
        $this->websiteName = $websiteName;
        $this->sendUserWelcomeEmailEnabled = $sendUserWelcomeEmailEnabled;
        $this->sendUserEnabledEmailEnabled = $sendUserEnabledEmailEnabled;
        $this->sendUserUsernameChangedEmailEnabled = $sendUserUsernameChangedEmailEnabled;
        $this->sendUserPasswordChangedEmailEnabled = $sendUserPasswordChangedEmailEnabled;
    }

    public function sendPasswordResetEmail(AbstractUser $user): void
    {
        $token = $user->getNewPasswordConfirmationToken();
        if (!$token) {
            throw new InvalidArgumentException('A new password confirmation token must be set to send the `password reset` email');
        }

        $userUsername = self::getUserUsername($user);
        $resetUrl = $this->pathToReferrerUrl(
            $token,
            $userUsername,
            'resetPath',
            $this->defaultPasswordResetPath
        );
        $email = $this->createEmailMessage(
            'Your password reset request',
            'user_forgot_password.html.twig',
            $user,
            [
                'reset_url' => $resetUrl,
            ]
        );
        $this->send($email);
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): void
    {
        $userUsername = self::getUserUsername($user);
        $verifyUrl = $this->getEmailConfirmationUrl($user, $userUsername);
        $email = $this->createEmailMessage(
            'Your password reset request',
            'user_verify_email.html.twig',
            $user,
            [
                'verify_url' => $verifyUrl,
            ]
        );
        $this->send($email);
    }

    public function sendUserWelcomeEmail(AbstractUser $user): void
    {
        if (!$this->sendUserWelcomeEmailEnabled) {
            return;
        }
        $userUsername = self::getUserUsername($user);
        try {
            $verifyUrl = $this->getEmailConfirmationUrl($user, $userUsername);
        } catch (InvalidArgumentException $exception) {
            // if we have not set the email verify token this will be thrown. this is an optional token though.
            $verifyUrl = null;
        }
        $email = $this->createEmailMessage(
            sprintf('Welcome to %s', $this->websiteName),
            'user_welcome.html.twig',
            $user,
            [
                'verify_url' => $verifyUrl,
            ]
        );
        $this->send($email);
    }

    public function sendUserEnabledEmail(AbstractUser $user): void
    {
        if (!$this->sendUserEnabledEmailEnabled) {
            return;
        }
        $email = $this->createEmailMessage(
            'Your account has been enabled',
            'user_enabled.html.twig',
            $user
        );
        $this->send($email);
    }

    public function sendUsernameChangedEmail(AbstractUser $user): void
    {
        if (!$this->sendUserUsernameChangedEmailEnabled) {
            return;
        }
        $email = $this->createEmailMessage(
            'Your username has been changed',
            'username_changed.html.twig',
            $user
        );
        $this->send($email);
    }

    public function sendPasswordChangedEmail(AbstractUser $user): void
    {
        if (!$this->sendUserPasswordChangedEmailEnabled) {
            return;
        }
        $email = $this->createEmailMessage(
            'Your password has been changed',
            'user_password_changed.html.twig',
            $user
        );
        $this->send($email);
    }

    private static function getUserEmail(AbstractUser $user): string
    {
        if (!($userEmail = $user->getEmailAddress())) {
            throw new InvalidArgumentException('The user must have an email address set to send them any email');
        }

        return $userEmail;
    }

    private static function getUserUsername(AbstractUser $user): string
    {
        if (!($userUsername = $user->getUsername())) {
            throw new InvalidArgumentException('The user must have a username set to send them any email');
        }

        return $userUsername;
    }

    private function createEmailMessage(string $subject, string $htmlTemplate, AbstractUser $user, array $context = [])
    {
        $defaultContext = [
            'user' => $user,
            'username' => self::getUserUsername($user),
            'website_name' => $this->websiteName,
        ];

        try {
            $toAddress = Address::fromString(self::getUserEmail($user));
        } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $exception) {
            $exception = new RfcComplianceException($exception->getMessage());
            throw $exception;
        }

        return (new TemplatedEmail())
            ->to($toAddress)
            ->subject($subject)
            ->htmlTemplate('@SilverbackApiComponent/emails/' . $htmlTemplate)
            ->context(array_merge($defaultContext, $context));
    }

    private function send(RawMessage $message): void
    {
        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $exception) {
            $exception = new MailerTransportException($exception->getMessage());
            $exception->appendDebug($exception->getDebug());
            throw $exception;
        }
    }

    private function getEmailConfirmationUrl(AbstractUser $user, string $userUsername): string
    {
        $token = $user->getNewEmailVerificationToken();
        if (!$token) {
            throw new InvalidArgumentException('A new email verification token must be set to send the `email verification` email');
        }

        return $this->pathToReferrerUrl(
            $token,
            $userUsername,
            'verifyPath',
            $this->defaultChangeEmailVerifyPath
        );
    }

    private function pathToReferrerUrl(string $token, string $username, string $queryKey, string $defaultPath): string
    {
        $request = $this->requestStack->getMasterRequest();
        $path = $request ? $request->query->get($queryKey, $defaultPath) : $defaultPath;
        $path = str_replace(['{{ token }}', '{{ username }}'], [$token, $username], $path);

        return $this->refererUrlHelper->getAbsoluteUrl($path);
    }
}
