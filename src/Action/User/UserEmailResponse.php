<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Action\User;

use Silverback\ApiComponentsBundle\Exception\RequestLimitReachedException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @author Daniel West <daniel@silverback.is>
 */
final class UserEmailResponse
{
    public static function sent(bool $success): Response
    {
        return self::uncached(new Response(null, $success ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE));
    }

    public static function tooManyRequests(RequestLimitReachedException $exception): Response
    {
        return self::uncached(new Response(null, Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $exception->getRetryAfter()]));
    }

    private static function uncached(Response $response): Response
    {
        $response->setCache([
            'private' => true,
            's_maxage' => 0,
            'max_age' => 0,
        ]);

        return $response;
    }
}
