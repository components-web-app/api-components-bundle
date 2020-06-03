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
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserFactory
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserRepository $userRepository;
    private TimestampedDataPersister $timestampedDataPersister;
    private UserPasswordEncoderInterface $passwordEncoder;
    private string $userClass;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UserRepository $userRepository, TimestampedDataPersister $timestampedDataPersister, UserPasswordEncoderInterface $passwordEncoder, string $userClass)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
        $this->timestampedDataPersister = $timestampedDataPersister;
        $this->passwordEncoder = $passwordEncoder;
        $this->userClass = $userClass;
    }

    public function create(string $username, string $password, string $email = null, bool $inactive = false, bool $superAdmin = false, bool $overwrite = false): void
    {
        if (!$email) {
            $email = $username;
        }

        /** @var AbstractUser|null $user */
        $user = $overwrite ? $this->userRepository->loadUserByUsername($username) : null;

        if (!$user) {
            $user = new $this->userClass();
        }

        $encodedPassword = $this->passwordEncoder->encodePassword($user, $password);

        $user
            ->setUsername($username)
            ->setPassword($encodedPassword)
            ->setEmailAddress($email)
            ->setEnabled(!$inactive)
            ->setEmailAddressVerified(true)
            ->setRoles(
                [
                    $superAdmin ? 'ROLE_SUPER_ADMIN' : 'ROLE_USER',
                ]
            );

        $this->timestampedDataPersister->persistTimestampedFields($user, true);
        $this->validator->validate($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
