<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Realtime;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RealtimePublishClient
{
    public const float TIMEOUT_SECONDS = 1.0;

    public static function bound(?HttpClientInterface $client = null): HttpClientInterface
    {
        return ($client ?? HttpClient::create())->withOptions(['timeout' => self::TIMEOUT_SECONDS, 'max_duration' => self::TIMEOUT_SECONDS]);
    }
}
