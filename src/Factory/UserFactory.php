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

namespace Silverback\ApiComponentBundle\Factory;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Repository\User\UserRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class UserFactory
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserRepository $userRepository;
    private string $userClass;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UserRepository $userRepository, string $userClass)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
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

        $user
            ->setUsername($username)
            ->setPlainPassword($password)
            ->setEmailAddress($email)
            ->setEnabled(!$inactive)
            ->setEmailAddressVerified(true)
            ->setRoles([
                $superAdmin ? 'ROLE_SUPER_ADMIN' : 'ROLE_USER',
            ]);

        $this->validator->validate($user);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
