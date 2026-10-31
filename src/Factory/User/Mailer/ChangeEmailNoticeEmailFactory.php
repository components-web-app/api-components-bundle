<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Factory\User\Mailer;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Utility\MaskedEmailAddress;
use Symfony\Component\Mime\RawMessage;

final class ChangeEmailNoticeEmailFactory extends AbstractUserEmailFactory
{
    public const MESSAGE_ID_PREFIX = 'cen';

    public function create(AbstractUser $user, array $context = []): ?RawMessage
    {
        if (!$this->enabled) {
            return null;
        }

        $this->initUser($user);

        $newEmailAddress = $user->getNewEmailAddress();
        if (null === $newEmailAddress || '' === $newEmailAddress) {
            throw new InvalidArgumentException('A `new email address` must be set to send the change email notice');
        }

        $context['masked_new_email_address'] = MaskedEmailAddress::of($newEmailAddress);

        return $this->createEmailMessage($context);
    }

    protected static function getContextKeys(): array
    {
        return array_merge(
            parent::getContextKeys(),
            [
                'masked_new_email_address',
            ]
        );
    }

    protected function getTemplate(): string
    {
        return 'user_change_email_notice.html.twig';
    }
}
