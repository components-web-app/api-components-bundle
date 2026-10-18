<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Page;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class CurrentVersionTest extends FunctionalTestCase
{
    use JsonTrait;

    #[DataProvider('documentationFormats')]
    public function test_i_can_detect_the_api_version(string $format): void
    {
        static::createClient()->request('GET', '/docs', ['headers' => ['accept' => $format, 'content-type' => $format]]);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeMatches('info.version', '/^1\.0\.0 \((?:dev-[a-zA-Z0-9\-]+|1\.0\.0\+no\-version\-set)@(?:[a-zA-Z0-9]+)?\)$/');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documentationFormats(): iterable
    {
        yield 'JSON' => ['application/json'];
        yield 'OpenAPI JSON' => ['application/vnd.openapi+json'];
        yield 'JSON-LD' => ['application/ld+json'];
    }
}
