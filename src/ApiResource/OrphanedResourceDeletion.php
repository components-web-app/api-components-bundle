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
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\OrphanedResourceDeletionStateProcessor;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource]
#[Post(
    uriTemplate: '/orphaned_resources/delete',
    status: 200,
    normalizationContext: ['groups' => ['OrphanedResourceDeletion:read']],
    denormalizationContext: ['groups' => ['OrphanedResourceDeletion:write']],
    security: "is_granted('ROLE_ADMIN')",
    processor: OrphanedResourceDeletionStateProcessor::class,
)]
#[Assert\Expression('(this.all === true) !== (this.iris !== null)', message: 'Send either "iris" or "all": true, not both.')]
final class OrphanedResourceDeletion
{
    /**
     * @var list<string>|null
     */
    #[Groups(['OrphanedResourceDeletion:write'])]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    public ?array $iris = null;

    #[Groups(['OrphanedResourceDeletion:write'])]
    public bool $all = false;

    /**
     * @param array{componentGroups: list<string>, componentPositions: list<string>, components: list<string>} $deleted
     * @param list<array{iri: string, reason: string}>                                                         $rejected
     */
    public function __construct(
        #[Groups(['OrphanedResourceDeletion:read'])]
        public array $deleted = ['componentGroups' => [], 'componentPositions' => [], 'components' => []],
        #[Groups(['OrphanedResourceDeletion:read'])]
        public array $rejected = [],
    ) {
    }
}
