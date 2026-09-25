<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\User;

use Psr\Container\ContainerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Exception\UnparseableRequestHeaderException;
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

    public function sendPasswordResetEmail(AbstractUser $user): bool
    {
        return $this->sendJobEmail(
            $user,
            PasswordResetEmailFactory::class,
            static fn (?\DateTime $requestedAt) => $user->setPasswordRequestedAt($requestedAt),
        );
    }

    public function sendChangeEmailConfirmationEmail(AbstractUser $user): bool
    {
        return $this->sendJobEmail(
            $user,
            ChangeEmailConfirmationEmailFactory::class,
            static fn (?\DateTime $requestedAt) => $user->setNewEmailAddressChangeRequestedAt($requestedAt),
        );
    }

    public function sendEmailVerifyEmail(AbstractUser $user): bool
    {
        return $this->sendJobEmail(
            $user,
            VerifyEmailFactory::class,
            static fn (?\DateTime $requestedAt) => $user->setEmailAddressVerificationRequestedAt($requestedAt),
        );
    }

    public function sendEmailVerifyEmailAfterWrite(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->sendEmailVerifyEmail($user));
    }

    public function sendChangeEmailConfirmationEmailAfterWrite(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->sendChangeEmailConfirmationEmail($user));
    }

    public function sendWelcomeEmail(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->send($this->container->get(WelcomeEmailFactory::class)->create($user, $this->context)));
    }

    public function sendUserEnabledEmail(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->send($this->container->get(UserEnabledEmailFactory::class)->create($user, $this->context)));
    }

    public function sendUsernameChangedEmail(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->send($this->container->get(UsernameChangedEmailFactory::class)->create($user, $this->context)));
    }

    public function sendPasswordChangedEmail(AbstractUser $user): bool
    {
        return $this->afterWrite($user, fn (): bool => $this->send($this->container->get(PasswordChangedEmailFactory::class)->create($user, $this->context)));
    }

    /**
     * @param \Closure(?\DateTime): mixed $setRequestedAt
     */
    private function sendJobEmail(AbstractUser $user, string $factoryClass, \Closure $setRequestedAt): bool
    {
        $email = $this->container->get($factoryClass)->create($user, $this->context);

        if (!$email) {
            $setRequestedAt(null);

            return false;
        }

        if (!$this->send($email)) {
            return false;
        }

        $setRequestedAt(new \DateTime());
        $this->container->get('doctrine.orm.entity_manager')->flush();

        return true;
    }

    /**
     * @param \Closure(): bool $send
     */
    private function afterWrite(AbstractUser $user, \Closure $send): bool
    {
        try {
            return $send();
        } catch (UnparseableRequestHeaderException $exception) {
            if ($this->container->has('logger')) {
                $this->container->get('logger')->error(\sprintf('The email to the user `%s` was not sent: %s', $user->getUsername(), $exception->getMessage()), [
                    'user' => $user->getUsername(),
                    'exception' => $exception,
                ]);
            }

            return false;
        }
    }

    private function send(?RawMessage $message): bool
    {
        if (null === $message) {
            return false;
        }

        try {
            $this->mailer->send($message);
        } catch (TransportExceptionInterface $exception) {
            $exception = new MailerTransportException($exception->getMessage());
            $exception->appendDebug($exception->getDebug());
            if ($logger = $this->container->get('logger')) {
                $logger->error($exception->getMessage(), [
                    'exception' => $exception,
                ]);

                return false;
            }
            throw $exception;
        }

        return true;
    }
}
