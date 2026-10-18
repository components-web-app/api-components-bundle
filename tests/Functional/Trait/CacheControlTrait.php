<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

trait CacheControlTrait
{
    protected static function sharedMaxAge(): int
    {
        $cacheControl = (string) self::getClient()->getResponse()->headers->get('cache-control');
        self::assertMatchesRegularExpression('/s-maxage=\d+/', $cacheControl, \sprintf('The Cache-Control header "%s" has no s-maxage directive.', $cacheControl));
        preg_match('/s-maxage=(\d+)/', $cacheControl, $matches);

        return (int) $matches[1];
    }

    protected static function assertSharedMaxAgeAtMost(int $seconds): void
    {
        self::assertLessThanOrEqual($seconds, static::sharedMaxAge());
    }

    protected static function assertSharedMaxAgeAtLeast(int $seconds): void
    {
        self::assertGreaterThanOrEqual($seconds, static::sharedMaxAge());
    }

    protected static function assertResponseHeaderIsSecondsBetween(string $name, int $min, int $max): void
    {
        $value = self::getClient()->getResponse()->headers->get($name);
        self::assertNotNull($value, \sprintf('The response has no %s header.', $name));
        self::assertMatchesRegularExpression('/^\d+$/', $value, \sprintf('The %s header "%s" is not a number of seconds.', $name, $value));
        self::assertGreaterThanOrEqual($min, (int) $value);
        self::assertLessThanOrEqual($max, (int) $value);
    }
}
