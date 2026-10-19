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

final class OpenapiCompatibilityTest extends FunctionalTestCase
{
    use JsonTrait;

    public function test_i_can_view_the_swagger_api_docs(): void
    {
        static::createClient()->request('GET', '/', ['headers' => ['accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8']]);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_the_api_version_string_is_extended_with_the_bundle_package_version_in_parentheses(): void
    {
        static::createClient()->request('GET', '/docs.json', ['headers' => ['accept' => 'application/json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('(', (string) self::jsonNode('info.version'));
    }

    public function test_concrete_component_endpoints_are_included_in_the_openapi_paths(): void
    {
        static::createClient()->request('GET', '/docs.json', ['headers' => ['accept' => 'application/json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('/component/dummy_components', self::jsonNode('paths'));
        self::assertArrayHasKey('get', self::jsonNode('paths')['/component/dummy_components']);
    }

    #[DataProvider('removedPaths')]
    public function test_the_paths_of_abstract_and_internal_resources_are_emptied_from_the_openapi_documentation(string $path): void
    {
        static::createClient()->request('GET', '/docs.json', ['headers' => ['accept' => 'application/json']]);

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey($path, self::jsonNode('paths'));
        self::assertSame([], (array) self::jsonNode('paths')[$path]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function removedPaths(): iterable
    {
        yield 'an abstract component' => ['/_/abstract_components/{id}'];
        yield 'the usage of an abstract component' => ['/_/abstract_components/{id}/usage'];
        yield 'abstract page data' => ['/_/abstract_page_datas/{id}'];
        yield 'a media object' => ['/_/media_objects/{id}'];
    }
}
