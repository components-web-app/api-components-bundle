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

namespace Silverback\ApiComponentsBundle\Manager\User;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Mailer\UserMailer;
use Silverback\ApiComponentsBundle\Repository\User\UserRepository;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PasswordManager
{
    private UserMailer $userMailer;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private UserRepository $userRepository;
    private int $tokenTtl;

    public function __construct(
        UserMailer $userMailer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        int $tokenTtl = 8600
    ) {
        $this->userMailer = $userMailer;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->userRepository = $userRepository;
        $this->tokenTtl = $tokenTtl;
    }

    public function requestResetEmail(string $usernameQuery): void
    {
        $user = $this->userRepository->findOneBy(['username' => $usernameQuery]);
        if (!$user) {
            throw new NotFoundHttpException();
        }

        if ($user->isPasswordRequestLimitReached($this->tokenTtl)) {
            return;
        }

        $username = $user->getUsername();
        if (!$username) {
            throw new InvalidArgumentException(sprintf('The entity %s should have a username set to send a password reset email.', AbstractUser::class));
        }
        $user->setNewPasswordConfirmationToken(TokenGenerator::generateToken());
        $user->setPasswordRequestedAt(new DateTime());
        $this->userMailer->sendPasswordResetEmail($user);
        $this->entityManager->flush();
    }

    public function passwordReset(string $username, string $token, string $newPassword): void
    {
        $user = $this->userRepository->findOneByPasswordResetToken(
            $username,
            $token
        );
        if (!$user) {
            throw new NotFoundHttpException();
        }

        $user->setPlainPassword($newPassword);
        $user->setNewPasswordConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $errors = $this->validator->validate($user, null, ['password_reset']);
        if (\count($errors)) {
            throw new AuthenticationException('The password entered is not valid');
        }
        $this->persistPlainPassword($user);
    }

    public function persistPlainPassword(AbstractUser $user): AbstractUser
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $user->eraseCredentials();

        return $user;
    }
}
