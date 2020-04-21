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

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\MailerTransportException;
use Silverback\ApiComponentBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentBundle\Factory\Mailer\User\WelcomeEmailFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserMailer implements ServiceSubscriberInterface
{
    private MailerInterface $mailer;
    private ContainerInterface $container;
    private array $context;

    public function __construct(
        MailerInterface $mailer,
        ContainerInterface $container,
        array $context = []
    ) {
        $this->mailer = $mailer;
        $this->container = $container;
        $this->context = $context;
    }

    public static function getSubscribedServices(): array
    {
        return [
            PasswordResetEmailFactory::class,
            ChangeEmailVerificationEmailFactory::class,
            WelcomeEmailFactory::class,
            UserEnabledEmailFactory::class,
            UsernameChangedEmailFactory::class,
            PasswordChangedEmailFactory::class,
        ];
    }

    public function sendPasswordResetEmail(AbstractUser $user): void
    {
        $email = $this->container->get(PasswordResetEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): void
    {
        $email = $this->container->get(ChangeEmailVerificationEmailFactory::class)->create($user, $this->context);
        $this->send($email);
    }

    public function sendUserWelcomeEmail(AbstractUser $user): void
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
