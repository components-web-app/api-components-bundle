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

namespace Silverback\ApiComponentBundle\Factory\Mailer\User;

use Silverback\ApiComponentBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentBundle\Exception\InvalidArgumentException;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class ChangeEmailVerificationEmailFactory extends AbstractUserEmailFactory
{
    public function create(AbstractUser $user, array $context = []): ?RawMessage
    {
        if (!$this->enabled) {
            return null;
        }

        $this->initUser($user);

        $token = $user->getNewEmailVerificationToken();
        if (!$token) {
            throw new InvalidArgumentException('A `new email verification token` must be set to send the verification email');
        }

        $context['redirect_url'] = $this->getTokenUrl($token, $user->getUsername());

        return $this->createEmailMessage($context);
    }

    protected static function getRequiredContextKeys(): ?array
    {
        return array_merge(parent::getRequiredContextKeys(), [
            'redirect_url',
        ]);
    }

    protected function getTemplate(): string
    {
        return 'user_change_email_verification.html.twig';
    }
}
