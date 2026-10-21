<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Realtime\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\RealtimeEventStateProcessor;

#[ApiResource]
#[Post(
    uriTemplate: '/realtime',
    name: self::OPERATION_NAME,
    status: 202,
    read: true,
    input: false,
    output: false,
    security: self::SECURITY,
    processor: RealtimeEventStateProcessor::class,
)]
class RealtimeEvent
{
    public const string OPERATION_NAME = 'cwa_realtime_event';
    public const string SECURITY = "is_granted('ROLE_ADMIN')";
}
