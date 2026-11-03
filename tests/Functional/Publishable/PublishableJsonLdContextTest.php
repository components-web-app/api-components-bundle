<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Publishable;

use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;

final class PublishableJsonLdContextTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;

    #[DataProvider('contexts')]
    public function test_a_json_ld_context_is_served_to_an_anonymous_caller(string $shortName): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/contexts/' . $shortName);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('@context');
    }

    #[DataProvider('contexts')]
    public function test_a_json_ld_context_is_served_to_an_admin(string $shortName): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);

        self::requestJsonLd($client, 'GET', '/contexts/' . $shortName);

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeExists('@context');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function contexts(): iterable
    {
        yield 'a publishable component' => ['DummyPublishableComponent'];
        yield 'a page' => ['Page'];
    }
}
