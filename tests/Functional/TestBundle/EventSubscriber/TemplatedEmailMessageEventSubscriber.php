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

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\EventSubscriber;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;

class TemplatedEmailMessageEventSubscriber implements EventSubscriberInterface
{
    private array $messageEvents = [];

    public static function getSubscribedEvents(): array
    {
        return [
            MessageEvent::class => [
                'addMessageEvent', 10,
            ],
        ];
    }

    public function addMessageEvent(MessageEvent $messageEvent): void
    {
        $message = $messageEvent->getMessage();
        if (!$message instanceof TemplatedEmail) {
            return;
        }
        $this->messageEvents[] = [$messageEvent, $message->getContext()];
    }

    /**
     * @return MessageEvent[]
     */
    public function getMessageEvents(): array
    {
        return array_map(static function (array $eventAndContext) {
            $event = $eventAndContext[0];
            $clonedMessage = clone $event->getMessage();
            $clonedMessage->context($eventAndContext[1]);
            $event->setMessage($clonedMessage);

            return $event;
        }, $this->messageEvents);
    }

    public function getMessages(): iterable
    {
        foreach ($this->getMessageEvents() as $messageEvent) {
            yield $messageEvent->getMessage();
        }
    }
}
