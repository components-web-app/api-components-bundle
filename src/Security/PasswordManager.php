<?php

namespace Silverback\ApiComponentBundle\Security;

use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\User\UserInterface;
use Silverback\ApiComponentBundle\Exception\InvalidEntityException;
use Silverback\ApiComponentBundle\Mailer\Mailer;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PasswordManager
{
    private $mailer;
    private $entityManager;
    private $validator;
    private $passwordEncoder;
    private $tokenGenerator;
    private $tokenTtl;

    public function __construct(
        Mailer $mailer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserPasswordEncoderInterface $passwordEncoder,
        TokenGenerator $tokenGenerator,
        int $tokenTtl
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->passwordEncoder = $passwordEncoder;
        $this->tokenGenerator = $tokenGenerator;
        $this->tokenTtl = $tokenTtl;
    }

    public function requestResetEmail(UserInterface $user, string $resetUrl): void
    {
        if ($user->isPasswordRequestLimitReached($this->tokenTtl)) {
            return;
        }
        $user->setPasswordResetConfirmationToken($this->tokenGenerator->generateToken());
        $user->setPasswordRequestedAt(new \DateTime());
        if ($this->mailer->passwordResetEmail($user, $resetUrl)) {
            $this->entityManager->flush();
        }
    }

    /**
     * @param UserInterface $user
     * @param string $newPassword
     */
    public function passwordReset(UserInterface $user, string $newPassword): void
    {
        $user->setPlainPassword($newPassword);
        $user->setPasswordResetConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $errors = $this->validator->validate($user, null, ['password_reset']);
        if (\count($errors)) {
            throw new InvalidEntityException($errors, 'The password entered is not valid');
        }
        $this->persistPlainPassword($user);
    }

    public function persistPlainPassword(UserInterface $user): UserInterface
    {
        $password = $this->passwordEncoder->encodePassword($user, $user->getPlainPassword());
        $user->setPassword($password);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $user->eraseCredentials();
        return $user;
    }
}
