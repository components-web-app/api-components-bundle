<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Factory\OrphanedResource;

use Silverback\ApiComponentsBundle\Helper\OrphanedResource\OrphanedResourceReportChange;
use Silverback\ApiComponentsBundle\Security\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Twig\Environment;

class OrphanedResourcesChangedEmailFactory
{
    public const string MESSAGE_ID_PREFIX = 'orc';
    public const string TEMPLATE = '@SilverbackApiComponents/emails/orphaned_resources_changed.html.twig';

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly string $subject,
        private readonly array $context = [],
    ) {
    }

    /**
     * @param list<Address> $recipients
     */
    public function create(OrphanedResourceReportChange $change, array $recipients, ?string $adminUrl): TemplatedEmail
    {
        $context = array_replace($this->context, [
            'counts' => $change->getCounts(),
            'added' => $change->getAdded(),
            'resolved_count' => $change->getResolvedCount(),
            'admin_url' => $adminUrl,
        ]);

        $email = (new TemplatedEmail())
            ->to(...$recipients)
            ->subject($this->twig->createTemplate($this->subject)->render($context))
            ->htmlTemplate(self::TEMPLATE)
            ->context($context);
        $email->getHeaders()->addTextHeader('X-Message-ID', \sprintf('%s-%s', self::MESSAGE_ID_PREFIX, TokenGenerator::generateToken()));

        return $email;
    }
}
