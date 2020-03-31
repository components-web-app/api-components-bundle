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

namespace Silverback\ApiComponentBundle\Security;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PasswordManager
{
    private MailerInterface $mailer;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private TokenGenerator $tokenGenerator;
    private RequestStack $requestStack;
    private string $websiteEmailAddress;
    private int $tokenTtl;

    public function __construct(
        MailerInterface $mailer,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        TokenGenerator $tokenGenerator,
        RequestStack $requestStack,
        string $websiteEmailAddress = 'no@email.com',
        int $tokenTtl = 8600
    ) {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->tokenGenerator = $tokenGenerator;
        $this->requestStack = $requestStack;
        $this->websiteEmailAddress = $websiteEmailAddress;
        $this->tokenTtl = $tokenTtl;
    }

    public function requestResetEmail(User $user, string $resetUrl): void
    {
        if ($user->isPasswordRequestLimitReached($this->tokenTtl)) {
            return;
        }
        $confirmationToken = $user->getPasswordResetConfirmationToken();
        if (!$confirmationToken) {
            throw new InvalidParameterException(sprintf('The entity %s should have a confirmation token set to send a password reset email.', User::class));
        }
        $username = $user->getUsername();
        if (!$username) {
            throw new InvalidParameterException(sprintf('The entity %s should have a username set to send a password reset email.', User::class));
        }
        $user->setPasswordResetConfirmationToken($this->tokenGenerator->generateToken());
        $user->setPasswordRequestedAt(new DateTime());
        $this->passwordResetEmail($user, $this->pathToAppUrl($resetUrl, $confirmationToken, $username));
        $this->entityManager->flush();
    }

    private function passwordResetEmail(User $user, string $resetUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(Address::fromString($this->websiteEmailAddress))
            ->to(Address::fromString($user->getUsername()))
            ->subject('Your password reset request')
            // path of the Twig template to render
            ->htmlTemplate('api-component-bundle/emails/forgot_password.html.twig')
            // pass variables (name => value) to the template
            ->context([
                'user' => $user,
                'reset_url' => $resetUrl,
            ]);
        $this->mailer->send($email);
    }

    public function passwordReset(User $user, string $newPassword): void
    {
        $user->setPlainPassword($newPassword);
        $user->setPasswordResetConfirmationToken(null);
        $user->setPasswordRequestedAt(null);
        $errors = $this->validator->validate($user, null, ['password_reset']);
        if (\count($errors)) {
            throw new AuthenticationException($errors, 'The password entered is not valid');
        }
        $this->persistPlainPassword($user);
    }

    public function persistPlainPassword(User $user): User
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $user->eraseCredentials();

        return $user;
    }

    private function pathToAppUrl(string $path, string $token, string $email): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $urlParts = $request ? parse_url($request->headers->get('referer')) : ['scheme' => 'https', 'host' => 'no-referrer'];
        $path = str_replace(['{{ token }}', '{{ email }}'], [$token, $email], $path);

        return sprintf(
            '%s://%s/%s',
            $urlParts['scheme'],
            $urlParts['host'],
            ltrim($path, '/')
        );
    }
}
