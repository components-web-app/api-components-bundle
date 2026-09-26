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

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Repository\User\FindUserByUsernameTrait;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class EmailAddressManager
{
    use FindUserByUsernameTrait;

    private EntityManagerInterface $entityManager;
    private UserRepositoryInterface $userRepository;
    private PasswordHasherFactoryInterface $passwordHasherFactory;
    private UserDataProcessor $userDataProcessor;
    private UserWriteNotifier $userWriteNotifier;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepositoryInterface $userRepository,
        PasswordHasherFactoryInterface $passwordHasherFactory,
        UserDataProcessor $userDataProcessor,
        UserWriteNotifier $userWriteNotifier,
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->passwordHasherFactory = $passwordHasherFactory;
        $this->userDataProcessor = $userDataProcessor;
        $this->userWriteNotifier = $userWriteNotifier;
    }

    public function confirmNewEmailAddress(string $username, string $email, string $token): void
    {
        if ('' === $email) {
            throw new InvalidArgumentException('User not found');
        }
        $user = $this->userRepository->findOneByUsernameAndNewEmailAddress($username, $email);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $previousUser = clone $user;
        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);
        if (!$hasher->verify($user->getNewEmailConfirmationToken(), $token)) {
            throw new InvalidArgumentException('Invalid token');
        }

        $existingUser = $this->userRepository->findExistingUserByNewEmail($user);
        if ($existingUser) {
            $user
                ->setNewEmailAddress(null)
                ->setNewEmailConfirmationToken(null);

            $this->entityManager->flush();
            throw new UnexpectedValueException('Another user now exists with that email. Verification aborted.');
        }

        $user
            ->setEmailAddress($user->getNewEmailAddress())
            ->setNewEmailAddress(null)
            ->setEmailAddressVerified(true)
            ->setNewEmailConfirmationToken(null);

        $this->userDataProcessor->processChanges($user, $previousUser);
        $this->entityManager->flush();
        $this->userWriteNotifier->notify($user, $previousUser);
    }

    public function verifyEmailAddress(string $username, string $token): void
    {
        $user = $this->findUserByUsernameIn($this->userRepository, $username);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        $hasher = $this->passwordHasherFactory->getPasswordHasher($user);
        if (!$hasher->verify($user->getEmailAddressVerifyToken(), $token)) {
            throw new InvalidArgumentException('Invalid token');
        }

        $user->setEmailAddressVerified(true);
        $this->entityManager->flush();
    }
}
