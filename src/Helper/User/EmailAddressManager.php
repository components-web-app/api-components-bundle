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

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\EventListener\Api\UserEventListener;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class EmailAddressManager
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private EncoderFactoryInterface $encoderFactory;
    private UserDataProcessor $userDataProcessor;
    private UserEventListener $userEventListener;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        EncoderFactoryInterface $encoderFactory,
        UserDataProcessor $userDataProcessor,
        UserEventListener $userEventListener
    ) {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->encoderFactory = $encoderFactory;
        $this->userDataProcessor = $userDataProcessor;
        $this->userEventListener = $userEventListener;
    }

    public function confirmNewEmailAddress(string $username, string $email, string $token): void
    {
        $user = $this->userRepository->findOneByUsernameAndNewEmailAddress($username, $email);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }
        $previousUser = clone $user;
        $encoder = $this->encoderFactory->getEncoder($user);
        if (!$encoder->isPasswordValid($user->getNewEmailConfirmationToken(), $token, $user->getSalt())) {
            throw new InvalidArgumentException('Invalid token');
        }

        // Check if another user now exists with this new email address before persisting!
        $existingUser = $this->userRepository->findExistingUserByNewEmail($user);
        if ($existingUser) {
            $user
                ->setNewEmailAddress(null)
                ->setNewEmailConfirmationToken(null);

            $this->entityManager->flush();
            throw new UnexpectedValueException('Another user no exists with that email. Verification aborted.');
        }

        $user
            ->setEmailAddress($user->getNewEmailAddress())
            ->setNewEmailAddress(null)
            ->setEmailAddressVerified(false);

        $this->userDataProcessor->processChanges($user, $previousUser);
        $this->entityManager->flush();
        $this->userEventListener->postWrite($user, $previousUser);
    }

    public function verifyEmailAddress(string $username, string $token): void
    {
        $user = $this->userRepository->findOneBy([
            'username' => $username,
        ]);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        $encoder = $this->encoderFactory->getEncoder($user);
        if (!$encoder->isPasswordValid($user->getEmailAddressVerifyToken(), $token, $user->getSalt())) {
            throw new InvalidArgumentException('Invalid token');
        }

        $user->setEmailAddressVerified(true);
        $this->entityManager->flush();
    }
}
