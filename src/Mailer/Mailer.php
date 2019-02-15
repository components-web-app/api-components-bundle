<?php

namespace Silverback\ApiComponentBundle\Mailer;

use Silverback\ApiComponentBundle\Entity\User\User;
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
        $resetUrl = $this->pathToAppUrl(
            $resetUrl,
            $user->getPasswordResetConfirmationToken(),
            $user->getUsername()
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
        $confirmUrl = $this->pathToAppUrl(
            $confirmUrl,
            $user->getUsernameConfirmationToken(),
            $user->getNewUsername()
        );
        $message = (new \Swift_Message('Confirm change of username'))
            ->setFrom($this->fromEmailAddress)
            ->setTo($user->getNewUsername())
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
