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

namespace Silverback\ApiComponentsBundle\Factory\User;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Repository\User\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserFactory
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserRepositoryInterface $userRepository;
    private TimestampedDataPersister $timestampedDataPersister;
    private UserPasswordHasherInterface $passwordHasher;
    private string $userClass;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UserRepositoryInterface $userRepository, TimestampedDataPersister $timestampedDataPersister, UserPasswordHasherInterface $passwordHasher, string $userClass)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
        $this->timestampedDataPersister = $timestampedDataPersister;
        $this->passwordHasher = $passwordHasher;
        $this->userClass = $userClass;
    }

    public function create(string $username, string $password, string $email = null, bool $inactive = false, bool $superAdmin = false, bool $admin = false, bool $overwrite = false): void
    {
        if (!$email) {
            $email = $username;
        }

        /** @var AbstractUser|null $user */
        $user = $overwrite ? $this->userRepository->loadUserByIdentifier($username) : null;

        if (!$user) {
            $user = new $this->userClass();
        }

        $encodedPassword = $this->passwordHasher->hashPassword($user, $password);

        $roles = ['ROLE_USER'];
        if ($superAdmin) {
            $roles = ['ROLE_SUPER_ADMIN'];
        } elseif ($admin) {
            $roles = ['ROLE_ADMIN'];
        }

        $user
            ->setUsername($username)
            ->setPassword($encodedPassword)
            ->setEmailAddress($email)
            ->setEnabled(!$inactive)
            ->setEmailAddressVerified(true)
            ->setRoles($roles);

        $this->timestampedDataPersister->persistTimestampedFields($user, true);
        $this->validator->validate($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
