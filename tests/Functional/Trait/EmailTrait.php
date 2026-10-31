<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\User;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\EventSubscriber\TemplatedEmailMessageEventSubscriber;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\DataCollector\MessageDataCollector;

trait EmailTrait
{
    protected static function setEmailLinkDefaultOrigin(string $origin): void
    {
        $_SERVER['EMAIL_LINK_DEFAULT_ORIGIN'] = $_ENV['EMAIL_LINK_DEFAULT_ORIGIN'] = $origin;
    }

    /**
     * @return list<TemplatedEmail>
     */
    protected static function sentTemplatedEmails(): array
    {
        return array_values(iterator_to_array(static::getContainer()->get(TemplatedEmailMessageEventSubscriber::class)->getMessages(), false));
    }

    protected static function assertNoEmailSent(): void
    {
        $profile = self::getClient()->getProfile();
        self::assertNotFalse($profile, 'The request was not profiled.');
        $collector = $profile->getCollector('mailer');
        \assert($collector instanceof MessageDataCollector);
        $messages = $collector->getEvents()->getMessages();

        self::assertCount(0, $messages, \sprintf('Expected no emails but %d were sent.', \count($messages)));
    }

    protected static function assertOneTemplatedEmailSentTo(string $emailAddress = 'user@example.com'): TemplatedEmail
    {
        $emails = static::sentTemplatedEmails();
        self::assertCount(1, $emails, \sprintf('%d emails were sent with the subjects "%s".', \count($emails), implode('", "', array_map(static fn (TemplatedEmail $email): ?string => $email->getSubject(), $emails))));
        $email = $emails[0];
        $context = $email->getContext();

        self::assertSame('New Website', $context['website_name'] ?? null);
        self::assertInstanceOf(User::class, $context['user'] ?? null);
        self::assertSame($emailAddress, $email->getHeaders()->get('to')?->getBodyAsString());
        self::assertSame('test@website.com', $email->getHeaders()->get('from')?->getBodyAsString());

        return $email;
    }

    protected static function assertTemplatedEmailSentTo(string $emailAddress): TemplatedEmail
    {
        $emails = array_values(array_filter(static::sentTemplatedEmails(), static fn (TemplatedEmail $email): bool => $email->getHeaders()->get('to')?->getBodyAsString() === $emailAddress));
        self::assertCount(1, $emails, \sprintf('%d emails were sent to %s.', \count($emails), $emailAddress));
        $email = $emails[0];
        $context = $email->getContext();

        self::assertSame('New Website', $context['website_name'] ?? null);
        self::assertInstanceOf(User::class, $context['user'] ?? null);
        self::assertSame('test@website.com', $email->getHeaders()->get('from')?->getBodyAsString());

        return $email;
    }

    protected static function sentEmailLink(): string
    {
        $emails = static::sentTemplatedEmails();
        self::assertCount(1, $emails, \sprintf('Expected 1 email, got %d.', \count($emails)));
        $context = $emails[0]->getContext();
        $link = $context['redirect_url'] ?? $context['login_url'] ?? null;
        self::assertIsString($link, 'The sent email carries no link.');

        return $link;
    }
}
