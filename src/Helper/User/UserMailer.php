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
use Silverback\ApiComponentsBundle\Factory\Mailer\User\ChangeEmailVerificationEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\PasswordResetEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UserEnabledEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\UsernameChangedEmailFactory;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\WelcomeEmailFactory;
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

    public function __construct(MailerInterface $mailer, ContainerInterface $container, array $context = [])
    {
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

    public function sendChangeEmailVerificationEmail(AbstractUser $user): void
    {
        $email = $this->container->get(ChangeEmailVerificationEmailFactory::class)->create($user, $this->context);
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
