<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\RawMessage;

class SwitchableMailer implements MailerInterface
{
    private static bool $unreachable = false;

    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public static function setUnreachable(bool $unreachable): void
    {
        self::$unreachable = $unreachable;
    }

    public function send(RawMessage $message, ?Envelope $envelope = null): void
    {
        if (self::$unreachable) {
            throw new TransportException('Connection to the mail server could not be established.');
        }

        $this->mailer->send($message, $envelope);
    }
}
