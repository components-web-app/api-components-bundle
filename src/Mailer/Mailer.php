<?php

namespace Silverback\ApiComponentBundle\Mailer;

use Silverback\ApiComponentBundle\Entity\User\User;
use Silverback\ApiComponentBundle\Exception\InvalidParameterException;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class Mailer
{
    private $mailer;
    private $twig;
    private $requestStack;
    private $fromEmailAddress;
    private $logoSrc;
    private $websiteName;
    private $requestTimeout;

    public function __construct(
        \Swift_Mailer $mailer,
        Environment $twig,
        RequestStack $requestStack,
        string $fromEmailAddress,
        ?string $logoSrc,
        ?string $websiteName,
        int $requestTimeout
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->requestStack = $requestStack;
        $this->fromEmailAddress = $fromEmailAddress;
        $this->logoSrc = $logoSrc;
        $this->websiteName = $websiteName;
        $this->requestTimeout = gmdate('G', $requestTimeout);
        if ($this->requestTimeout > 1) {
            $this->requestTimeout .= ' hours';
        } else {
            $this->requestTimeout .= ' hour';
        }
    }

    public function getMessage(string $toEmail, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null)
    {
        $htmlTemplate = $this->twig->render('@SilverbackApiComponent/emails/template.html.twig', [
            'subject' => $subject,
            'body_html' => $htmlBody,
            'logo_src' => $this->logoSrc,
            'website_name' => $this->websiteName
        ]);
        $message = (new \Swift_Message($subject))
            ->setFrom($this->fromEmailAddress, $this->websiteName)
            ->setTo($toEmail)
            ->setBody($htmlTemplate, 'text/html')
            ->addPart($textBody, 'text/plain')
        ;
        if ($replyTo) {
            $message->setReplyTo($replyTo);
        }
        return $message;
    }

    public function sendEmail(string $toEmail, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): int
    {
        $message = $this->getMessage($toEmail, $subject, $htmlBody, $textBody, $replyTo);
        return $this->mailer->send($message);
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
        $subject = 'Password Reset Request';
        $htmlEmail = $this->twig->render(
            '@SilverbackApiComponent/emails/password_reset.html.twig',
            ['user' => $user, 'reset_url' => $resetUrl, 'website_name' => $this->websiteName, 'timeout' => $this->requestTimeout]
        );
        $textEmail = $this->twig->render(
            '@SilverbackApiComponent/emails/password_reset.txt.twig',
            ['user' => $user, 'reset_url' => $resetUrl, 'website_name' => $this->websiteName, 'timeout' => $this->requestTimeout]
        );

        return $this->sendEmail($user->getUsername(), $subject, $htmlEmail, $textEmail);
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
        $htmlEmail = $this->twig->render(
            '@SilverbackApiComponent/emails/new_username_confirmation.html.twig',
            ['user' => $user, 'confirm_url' => $confirmUrl, 'website_name' => $this->websiteName]
        );
        $textEmail = $this->twig->render(
            '@SilverbackApiComponent/emails/new_username_confirmation.txt.twig',
            ['user' => $user, 'confirm_url' => $confirmUrl, 'website_name' => $this->websiteName]
        );
        return $this->sendEmail($username, 'Confirm change of username', $htmlEmail, $textEmail);
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
