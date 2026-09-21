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
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RenderedHtmlPurgeStateProcessor;

#[ApiResource]
#[Post(
    uriTemplate: '/rendered_html/purge',
    status: 204,
    read: true,
    input: false,
    output: false,
    security: "is_granted('ROLE_ADMIN')",
    processor: RenderedHtmlPurgeStateProcessor::class,
)]
class RenderedHtmlPurge
{
}
