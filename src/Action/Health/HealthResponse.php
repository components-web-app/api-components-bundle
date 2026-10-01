<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Action\Health;

use Symfony\Component\HttpFoundation\JsonResponse;

final class HealthResponse
{
    /**
     * @param array<string, string> $body
     */
    public static function neverStored(array $body, int $status): JsonResponse
    {
        $response = new JsonResponse($body, $status);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
