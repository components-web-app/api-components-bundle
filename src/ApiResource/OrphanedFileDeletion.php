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
use ApiPlatform\Metadata\Post;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedFileDeletionStateProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/orphaned_files/delete',
    status: 200,
    normalizationContext: ['groups' => ['OrphanedFileDeletion:read']],
    denormalizationContext: ['groups' => ['OrphanedFileDeletion:write']],
    security: "is_granted('ROLE_ADMIN')",
    processor: OrphanedFileDeletionStateProcessor::class,
)]
#[Assert\Expression('(this.all === true) !== (this.paths !== null)', message: 'Send either "paths" or "all": true, not both.')]
final class OrphanedFileDeletion
{
    /**
     * @var list<string>|null
     */
    #[Groups(['OrphanedFileDeletion:write'])]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    public ?array $paths = null;

    #[Groups(['OrphanedFileDeletion:write'])]
    public bool $all = false;

    /**
     * @param list<array{adapter: string, path: string}> $deleted
     * @param list<array{path: string, reason: string}>  $rejected
     */
    public function __construct(
        #[Groups(['OrphanedFileDeletion:read'])]
        public array $deleted = [],
        #[Groups(['OrphanedFileDeletion:read'])]
        public array $rejected = [],
    ) {
    }
}
