<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Identifier;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\LogRecordsTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ResourceTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class InvalidUuidTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use LogRecordsTrait;
    use ResourceTrait;
    use SiteTrait;

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidUuidItemPaths(): iterable
    {
        yield 'a component' => ['/component/dummy_components/not-a-uuid'];
        yield 'a form' => ['/component/forms/not-a-uuid'];
        yield 'a page' => ['/_/pages/not-a-uuid'];
        yield 'page data' => ['/page_data/page_datas/not-a-uuid'];
        yield 'a layout' => ['/_/layouts/not-a-uuid'];
        yield 'a component group' => ['/_/component_groups/not-a-uuid'];
        yield 'a media object download' => ['/component/dummy_uploadables/not-a-uuid/download/file'];
        yield 'the literal {id} from the API docs' => ['/_/pages/%7Bid%7D'];
    }

    #[DataProvider('invalidUuidItemPaths')]
    public function test_an_item_url_whose_id_is_not_a_uuid_is_a_404(string $path): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', $path);

        self::assertResponseStatusCodeSame(404);
        self::assertNotLogged('a critical error', static fn (LogRecord $record): bool => Level::Critical->includes($record->level));
    }

    public function test_a_route_is_still_found_by_its_path(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/about');

        self::requestJsonLd($client, 'GET', '/_/routes//about');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('path', '/about');
    }

    public function test_a_route_path_that_does_not_exist_is_a_404(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/_/routes//missing');

        self::assertResponseStatusCodeSame(404);
    }

    public function test_a_manifest_is_still_found_by_its_route_path(): void
    {
        $client = static::createClient();
        $route = self::createRouteWithPage('/about');

        self::requestJsonLd($client, 'GET', '/_/resource_manifest//about');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('resource_iris[0].iri', self::iriOf($route));
    }

    public function test_a_component_group_is_still_found_by_its_reference(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        self::createComponentGroupWithComponents(1, 'header');

        self::requestJsonLd($client, 'GET', '/_/component_groups/header');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('reference', 'header');
    }

    public function test_the_password_reset_form_is_still_found_by_its_name(): void
    {
        $client = static::createClient();

        self::requestJsonLd($client, 'GET', '/component/forms/password_reset');

        self::assertResponseStatusCodeSame(200);
    }
}
