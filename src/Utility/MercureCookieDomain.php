<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Utility;

final class MercureCookieDomain
{
    public static function isOutside(string $requestHost, string $hubPublicUrl): bool
    {
        $hubHost = parse_url($hubPublicUrl, \PHP_URL_HOST);
        if (!\is_string($hubHost)) {
            return false;
        }

        $hubHost = strtolower($hubHost);
        $host = strtolower($requestHost);
        if ($hubHost === $host || str_ends_with($hubHost, '.' . $host)) {
            return false;
        }

        $segments = explode('.', $host);
        for ($i = 0, $length = \count($segments) - 1; $i < $length; ++$i) {
            $domain = implode('.', \array_slice($segments, $i));
            if ($domain === $hubHost || str_ends_with($hubHost, '.' . $domain)) {
                return false;
            }
        }

        return true;
    }
}
