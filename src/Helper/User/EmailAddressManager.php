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
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class EmailAddressManager
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, UserRepository $userRepository)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
    }

    public function verifyNewEmailAddress(string $username, string $email, string $token): void
    {
        $user = $this->userRepository->findOneByEmailVerificationToken($username, $email, $token);
        if (!$user) {
            throw new InvalidArgumentException('User not found');
        }

        // Check if another user now exists with this new email address before persisting!
        $existingUser = $this->userRepository->findExistingUserByNewEmail($user);
        if ($existingUser) {
            $user
                ->setNewEmailAddress(null)
                ->setNewEmailVerificationToken(null);

            $this->entityManager->flush();
            throw new UnexpectedValueException('Another user no exists with that email. Verification aborted.');
        }

        $user
            ->setEmailAddress($user->getNewEmailAddress())
            ->setNewEmailAddress(null)
            ->setEmailAddressVerified(true);

        $this->entityManager->flush();
    }
}
