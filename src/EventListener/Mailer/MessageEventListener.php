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

namespace Silverback\ApiComponentBundle\EventListener\Mailer;

use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class MessageEventListener
{
    private string $fromEmailAddress;

    public function __construct(string $fromEmailAddress)
    {
        $this->fromEmailAddress = $fromEmailAddress;
    }

    public function __invoke(MessageEvent $messageEvent): void
    {
        $message = $messageEvent->getMessage();
        if (!$message instanceof Email) {
            return;
        }
        $message->from(Address::fromString($this->fromEmailAddress));
    }
}
