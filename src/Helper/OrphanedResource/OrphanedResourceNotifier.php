<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Helper\OrphanedResource;

use Psr\Log\LoggerInterface;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\MailerTransportException;
use Silverback\ApiComponentsBundle\Factory\OrphanedResource\OrphanedResourcesChangedEmailFactory;
use Silverback\ApiComponentsBundle\Helper\RefererUrlResolver;
use Silverback\ApiComponentsBundle\Helper\RelativeUrlPath;
use Silverback\ApiComponentsBundle\Utility\EmailRecipientList;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Exception\InvalidArgumentException as MimeInvalidArgumentException;
use Symfony\Component\Mime\Exception\RfcComplianceException;

class OrphanedResourceNotifier
{
    /**
     * @param list<string>|string|null $recipients
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly OrphanedResourcesChangedEmailFactory $emailFactory,
        private readonly RefererUrlResolver $urlResolver,
        private readonly array|string|null $recipients = [],
        private readonly string $adminPagePath = '/_cwa/orphaned',
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function notify(OrphanedResourceReportChange $change): OrphanedResourceNotificationResult
    {
        $configuredRecipients = $this->getRecipients();
        if ([] === $configuredRecipients) {
            return OrphanedResourceNotificationResult::NoRecipients;
        }

        if (!$change->hasChanged()) {
            return OrphanedResourceNotificationResult::Unchanged;
        }

        $recipients = [];
        foreach ($configuredRecipients as $recipient) {
            try {
                $recipients[] = Address::create($recipient);
            } catch (RfcComplianceException|MimeInvalidArgumentException $exception) {
                $this->logger?->error(\sprintf('The orphaned resources notification was not sent: the recipient `%s` is not a valid email address', $recipient), [
                    'exception' => $exception,
                ]);

                return OrphanedResourceNotificationResult::Failed;
            }
        }

        try {
            $this->mailer->send($this->emailFactory->create($change, $recipients, $this->getAdminUrl()));
        } catch (TransportExceptionInterface $transportException) {
            $exception = new MailerTransportException($transportException->getMessage());
            $exception->appendDebug($transportException->getDebug());
            $this->logger?->error($exception->getMessage(), [
                'exception' => $exception,
            ]);

            return OrphanedResourceNotificationResult::Failed;
        }

        return OrphanedResourceNotificationResult::Sent;
    }

    /**
     * @return list<string>
     */
    private function getRecipients(): array
    {
        $recipients = \is_string($this->recipients) ? EmailRecipientList::split($this->recipients) : $this->recipients ?? [];

        return array_values(array_filter(array_map('trim', $recipients), static fn (string $recipient): bool => '' !== $recipient));
    }

    private function getAdminUrl(): ?string
    {
        try {
            return $this->urlResolver->getDefaultOriginUrl(RelativeUrlPath::fromConfiguration($this->adminPagePath));
        } catch (InvalidArgumentException $exception) {
            $this->logger?->warning(\sprintf('The orphaned resources notification is sent without a link to the admin page: %s', $exception->getMessage()), [
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
