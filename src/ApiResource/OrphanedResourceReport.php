<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedResourceReportStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedResourceReportRecord;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ApiResource]
#[Get(
    uriTemplate: '/orphaned_resources',
    cacheHeaders: ['public' => false, 'no_store' => true],
    security: "is_granted('ROLE_ADMIN')",
    provider: OrphanedResourceReportStateProvider::class,
)]
#[Post(
    uriTemplate: '/orphaned_resources/scan',
    status: 202,
    read: true,
    input: false,
    output: false,
    security: "is_granted('ROLE_ADMIN')",
    processor: OrphanedResourceScanStateProcessor::class,
)]
final readonly class OrphanedResourceReport
{
    /**
     * @param list<string> $componentGroups
     * @param list<string> $componentPositions
     * @param list<string> $components
     */
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => OrphanedResourceReportRecord::GENERATED_AT_FORMAT])]
        public \DateTimeImmutable $generatedAt,
        public array $componentGroups = [],
        public array $componentPositions = [],
        public array $components = [],
    ) {
    }
}
