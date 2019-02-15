<?php

namespace Silverback\ApiComponentBundle\Mailer;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class Mailer
{
    private $mailer;
    private $twig;
    private $requestStack;
    private $fromEmailAddress;

    public function __construct(
        \Swift_Mailer $mailer,
        Environment $twig,
        RequestStack $requestStack,
        string $fromEmailAddress
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->fromEmailAddress = $fromEmailAddress;
    }

    public function passwordResetEmail(User $user, string $resetUrl): int
    {
        $confirmationToken = $user->getPasswordResetConfirmationToken();
        if (!$confirmationToken) {
            throw new InvalidParameterException(sprintf('The entity %s should have a confirmation token set to send a password reset email.', User::class));
        }
        $username = $user->getUsername();
        if (!$username) {
            throw new InvalidParameterException(sprintf('The entity %s should have a username set to send a password reset email.', User::class));
        }
        $resetUrl = $this->pathToAppUrl(
            $resetUrl,
            $confirmationToken,
            $username
        );
        $message = (new \Swift_Message('Password Reset Request'))
            ->setFrom($this->fromEmailAddress)
            ->setTo($user->getUsername())
            ->setBody(
                $this->twig->render(
                    '@SilverbackApiComponent/emails/password_reset.html.twig',
                    ['user' => $user, 'reset_url' => $resetUrl]
                ),
                'text/html'
            )
            ->addPart(
                $this->twig->render(
                    '@SilverbackApiComponent/emails/password_reset.txt.twig',
                    ['user' => $user, 'reset_url' => $resetUrl]
                ),
                'text/plain'
            )
        ;
        return $this->mailer->send($message);
    }

    public function newUsernameConfirmation(User $user, string $confirmUrl): int
    {
        $confirmationToken = $user->getUsernameConfirmationToken();
        if (!$confirmationToken) {
            throw new InvalidParameterException(sprintf('The entity %s should have a confirmation token set to send a new user confirmation email.', User::class));
        }
        $username = $user->getNewUsername();
        if (!$username) {
            throw new InvalidParameterException(sprintf('The entity %s should have a new username set to send a new user confirmation email.', User::class));
        }

        $confirmUrl = $this->pathToAppUrl(
            $confirmUrl,
            $confirmationToken,
            $username
        );
        $message = (new \Swift_Message('Confirm change of username'))
            ->setFrom($this->fromEmailAddress)
            ->setTo($username)
            ->setBody(
                $this->twig->render(
                    '@SilverbackApiComponent/emails/new_username_confirmation.html.twig',
                    ['user' => $user, 'confirm_url' => $confirmUrl]
                ),
                'text/html'
            )
            ->addPart(
                $this->twig->render(
                    '@SilverbackApiComponent/emails/new_username_confirmation.txt.twig',
                    ['user' => $user, 'confirm_url' => $confirmUrl]
                ),
                'text/plain'
            )
        ;
        return $this->mailer->send($message);
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
