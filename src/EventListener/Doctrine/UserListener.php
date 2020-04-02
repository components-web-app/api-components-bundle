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

namespace Silverback\ApiComponentBundle\EventListener\Doctrine;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Mailer\UserMailer;
use Silverback\ApiComponentBundle\Security\TokenGenerator;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserListener
{
    private UserPasswordEncoderInterface $passwordEncoder;
    private UserMailer $userMailer;
    private TokenGenerator $tokenGenerator;
    private bool $initialEmailVerifiedState;
    private bool $verifyEmailOnRegister;
    private array $changeSet = [];

    public function __construct(
        UserPasswordEncoderInterface $passwordEncoder,
        UserMailer $userMailer,
        TokenGenerator $tokenGenerator,
        bool $initialEmailVerifiedState,
        bool $verifyEmailOnRegister
    ) {
        $this->passwordEncoder = $passwordEncoder;
        $this->userMailer = $userMailer;
        $this->tokenGenerator = $tokenGenerator;
        $this->initialEmailVerifiedState = $initialEmailVerifiedState;
        $this->verifyEmailOnRegister = $verifyEmailOnRegister;
    }

    public function prePersist(AbstractUser $user): void
    {
        $this->encodePassword($user);
        $user->setEmailAddressVerified($this->initialEmailVerifiedState);
        if (!$this->initialEmailVerifiedState) {
            $user->setNewEmailAddress($user->getEmailAddress());
            if (!$this->verifyEmailOnRegister) {
                $user->setNewEmailVerificationToken($confirmationToken = $this->tokenGenerator->generateToken());
            }
        }
    }

    public function postPersist(AbstractUser $user): void
    {
        $this->userMailer->sendUserWelcomeEmail($user);
    }

    public function preUpdate(AbstractUser $user, LifecycleEventArgs $args): void
    {
        $manager = $args->getEntityManager();
        $uow = $manager->getUnitOfWork();
        $userClassMetadata = $manager->getClassMetadata(AbstractUser::class);

        $passwordEncoded = $this->encodePassword($user);
        if ($passwordEncoded) {
            $this->recomputeUserChangeSet($uow, $userClassMetadata, $user);
        }

        $this->changeSet = $uow->getEntityChangeSet($user);

        if ($this->changeSet['newEmailAddress']) {
            $user->setNewEmailVerificationToken($confirmationToken = $this->tokenGenerator->generateToken());
            $this->recomputeUserChangeSet($uow, $userClassMetadata, $user);
            $this->changeSet = $uow->getEntityChangeSet($user);
        }
    }

    public function postUpdate(AbstractUser $user): void
    {
        if (isset($this->changeSet['enabled']) && !$this->changeSet['enabled'][0] && $user->isEnabled()) {
            $this->userMailer->sendUserEnabledEmail($user);
        }

        if (isset($this->changeSet['username'])) {
            $this->userMailer->sendUsernameChangedEmail($user);
        }

        if (isset($this->changeSet['password'])) {
            $this->userMailer->sendPasswordChangedEmail($user);
        }
    }

    private function recomputeUserChangeSet(UnitOfWork $uow, ClassMetadata $userClassMetadata, AbstractUser $user): void
    {
        $uow->recomputeSingleEntityChangeSet($userClassMetadata, $user);
    }

    private function encodePassword(AbstractUser $entity): bool
    {
        if (!$entity->getPlainPassword()) {
            return false;
        }
        $encoded = $this->passwordEncoder->encodePassword(
            $entity,
            $entity->getPlainPassword()
        );
        $entity->setPassword($encoded);
        $entity->eraseCredentials();

        return true;
    }
}
