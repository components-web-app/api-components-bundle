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

namespace Silverback\ApiComponentsBundle\Helper\User;

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\ChangeEmailConfirmationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\VerifyEmailFactory;
use Silverback\ApiComponentsBundle\Factory\User\Mailer\WelcomeEmailFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserMailer
{
    private MailerInterface $mailer;
    private ContainerInterface $container;
    private array $context;

    public function __construct(MailerInterface $mailer, ContainerInterface $container, array $context = [])
    {
        $this->mailer = $mailer;
        $this->container = $container;
        $this->context = $context;
    }

    public function sendPasswordResetEmail(AbstractUser $user): void
    {
        $email = $this->container->get(PasswordResetEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): void
    {
        $email = $this->container->get(ChangeEmailConfirmationEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendEmailVerifyEmail(AbstractUser $user): void
    {
        $email = $this->container->get(VerifyEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendWelcomeEmail(AbstractUser $user): void
    {
        $email = $this->container->get(WelcomeEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendUserEnabledEmail(AbstractUser $user): void
    {
        $email = $this->container->get(UserEnabledEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendUsernameChangedEmail(AbstractUser $user): void
    {
        $email = $this->container->get(UsernameChangedEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendPasswordChangedEmail(AbstractUser $user): void
    {
        $email = $this->container->get(PasswordChangedEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    private function send(?RawMessage $message): void
    {
        if (null === $message) {
            return;
        }

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $exception) {
            $exception = new MailerTransportException($exception->getMessage());
            $exception->appendDebug($exception->getDebug());
            throw $exception;
        }
    }
}
