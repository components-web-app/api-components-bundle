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
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\ResourceManifestStateProvider;
use Silverback\ApiComponentsBundle\Security\Voter\ResourceManifestVoter;

/**
 * @author Daniel West <daniel@silverback.is>
 */
#[ApiResource]
#[Get(
    uriTemplate: '/resource_manifest/{id}{._format}',
    requirements: ['id' => '(.+)'],
    provider: ResourceManifestStateProvider::class,
    normalizationContext: ['groups' => ['Route:manifest:read']],
    security: "is_granted('" . ResourceManifestVoter::READ_MANIFEST . "', object)"
)]
class ResourceManifest
{
    public object $entity;
}
