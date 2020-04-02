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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserMailer
{
    private MailerInterface $mailer;
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
        RequestStack $requestStack,
        string $websiteName,
        string $defaultPasswordResetPath,
        string $defaultChangeEmailVerifyPath,
        bool $sendUserWelcomeEmailEnabled = true,
        bool $sendUserEnabledEmailEnabled = true,
        bool $sendUserUsernameChangedEmailEnabled = true,
        bool $sendUserPasswordChangedEmailEnabled = true)
    {
        $this->mailer = $mailer;
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
        $userEmail = $this->getUserEmail($user);
        $userUsername = $this->getUserUsername($user);
        $token = $user->getNewPasswordConfirmationToken();
        if (!$token) {
            throw new InvalidParameterException('A new password confirmation token must be set to send the `password reset` email');
        }
        $resetUrl = $this->pathToReferrerUrl(
            $token,
            $userUsername,
            'resetPath',
            $this->defaultPasswordResetPath
        );
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_forgot_password.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
                'reset_url' => $resetUrl,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): void
    {
        $userEmail = $this->getUserEmail($user);
        $userUsername = $this->getUserUsername($user);
        $verifyUrl = $this->getEmailConfirmationUrl($user, $userUsername);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your password reset request')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_verify_email.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
                'verify_url' => $verifyUrl,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    public function sendUserWelcomeEmail(AbstractUser $user): void
    {
        if (!$this->sendUserWelcomeEmailEnabled) {
            return;
        }
        $userEmail = $this->getUserEmail($user);
        $userUsername = $this->getUserUsername($user);
        try {
            $verifyUrl = $this->getEmailConfirmationUrl($user, $userUsername);
        } catch (InvalidParameterException $exception) {
            // if we have not set the email verify token this will be thrown. this is optional though.
            $verifyUrl = null;
        }
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject(sprintf('Welcome to %s', $this->websiteName))
            ->htmlTemplate('@SilverbackApiComponent/emails/user_welcome.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
                'verify_url' => $verifyUrl,
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
        $userUsername = $this->getUserUsername($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your account has been enabled')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_enabled.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
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
        $userUsername = $this->getUserUsername($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your username has been changed')
            ->htmlTemplate('@SilverbackApiComponent/emails/username_changed.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
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
        $userUsername = $this->getUserUsername($user);
        $email = (new TemplatedEmail())
            ->to(Address::fromString($userEmail))
            ->subject('Your password has been changed')
            ->htmlTemplate('@SilverbackApiComponent/emails/user_password_changed.html.twig')
            ->context([
                'user' => $user,
                'username' => $userUsername,
                'website_name' => $this->websiteName,
            ]);
        $this->mailer->send($email);
    }

    private function getUserEmail(AbstractUser $user): string
    {
        if (!($userEmail = $user->getEmailAddress())) {
            throw new InvalidParameterException('The user must have an email address set to send them any email');
        }

        return $userEmail;
    }

    private function getUserUsername(AbstractUser $user): string
    {
        if (!($userUsername = $user->getUsername())) {
            throw new InvalidParameterException('The user must have a username set to send them any email');
        }

        return $userUsername;
    }

    private function getEmailConfirmationUrl(AbstractUser $user, string $userUsername): string
    {
        $token = $user->getNewEmailVerificationToken();
        if (!$token) {
            throw new InvalidParameterException('A new email confirmation token must be set to send the `email verification` email');
        }

        return $this->pathToReferrerUrl(
            $token,
            $userUsername,
            'verifyPath',
            $this->defaultChangeEmailVerifyPath
        );
    }

    private function pathToReferrerUrl(string $token, string $email, string $queryKey, string $defaultPath): string
    {
        $request = $this->requestStack->getMasterRequest();
        $path = $request ? $request->query->get($queryKey, $defaultPath) : $defaultPath;
        $urlParts = $request ? parse_url($request->headers->get('referer')) : ['scheme' => 'https', 'host' => 'no-referrer'];
        $path = str_replace(['{{ token }}', '{{ email }}'], [$token, $email], $path);

        return sprintf(
            '%s://%s/%s',
            $urlParts['scheme'],
            $urlParts['host'],
            ltrim($path, '/')
        );
    }
}
