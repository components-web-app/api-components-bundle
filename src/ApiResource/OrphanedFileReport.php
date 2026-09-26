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
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedFileScanStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\OrphanedFileReportStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\OrphanedFileReportRecord;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ApiResource]
#[Get(
    uriTemplate: '/orphaned_files',
    cacheHeaders: ['public' => false, 'no_store' => true],
    security: "is_granted('ROLE_ADMIN')",
    provider: OrphanedFileReportStateProvider::class,
)]
#[Post(
    uriTemplate: '/orphaned_files/scan',
    status: 202,
    read: true,
    input: false,
    output: false,
    security: "is_granted('ROLE_ADMIN')",
    processor: OrphanedFileScanStateProcessor::class,
)]
final readonly class OrphanedFileReport
{
    /**
     * @param list<array{adapter: string, path: string}>                   $orphanedFiles
     * @param list<array{resource: string, adapter: string, path: string}> $missingFiles
     * @param list<array{adapter: string, path: string}>                   $unknownFiles
     */
    public function __construct(
        #[Context([DateTimeNormalizer::FORMAT_KEY => OrphanedFileReportRecord::GENERATED_AT_FORMAT])]
        public \DateTimeImmutable $generatedAt,
        public array $orphanedFiles = [],
        public array $missingFiles = [],
        public array $unknownFiles = [],
    ) {
    }
}
