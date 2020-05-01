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

namespace Silverback\ApiComponentsBundle\Tests\Functional\Factory\Mailer\User;

use Silverback\ApiComponentsBundle\Entity\User\AbstractUser;
use Silverback\ApiComponentsBundle\Factory\Mailer\User\AbstractUserEmailFactory;
use Symfony\Component\Mime\RawMessage;

class DummyUserEmailFactory extends AbstractUserEmailFactory
{
    public function create(AbstractUser $user, array $context = []): ?RawMessage
    {
        $this->initUser($user);

        return $this->createEmailMessage($context);
    }

    public function dummyGetTokenUrl(AbstractUser $user): string
    {
        return $this->getTokenUrl('my_token', $user->getUsername());
    }

    protected static function getContextKeys(): ?array
    {
        return [
            'website_name',
            'test_key',
        ];
    }

    protected function getTemplate(): string
    {
        return 'template.html.twig';
    }
}
