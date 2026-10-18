<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Route;

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\JsonTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\PublishableTrait;

final class SearchParameterTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use JsonTrait;
    use PublishableTrait;
    use RouteSiteTrait;

    private const string FUTURE = '2999-01-01T00:00:00+00:00';

    public function test_a_filtered_anonymous_route_collection_still_excludes_a_scheduled_route(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes?search=launch');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_filtered_anonymous_route_collection_still_excludes_a_draft_route(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');
        self::setRouteLiveAt('/launch', null);

        self::requestJsonLd($client, 'GET', '/_/routes?search=launch');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_a_filtered_anonymous_route_collection_still_excludes_a_route_gated_by_its_ancestor(): void
    {
        $client = static::createClient();
        self::createPageDataNestedWithinRoute('/conference/programme', '/conference');
        self::setRouteLiveAt('/conference', new \DateTimeImmutable(self::FUTURE));

        self::requestJsonLd($client, 'GET', '/_/routes?search=programme');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeNotExists('member[0]');
    }

    public function test_the_route_search_parameter_matches_part_of_the_path(): void
    {
        $client = static::createClient();
        self::createRouteWithPageAndRedirect('/launch', '/contact');

        self::requestJsonLd($client, 'GET', '/_/routes?search=LAUN');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/launch');
    }

    public function test_a_filtered_anonymous_route_collection_still_returns_a_live_route(): void
    {
        $client = static::createClient();
        self::createRouteWithPage('/launch');

        self::requestJsonLd($client, 'GET', '/_/routes?search=launch');

        self::assertResponseStatusCodeSame(200);
        self::assertJsonNodeSame('totalItems', 1);
        self::assertJsonNodeSame('member[0].path', '/launch');
    }

    public function test_a_filtered_collection_does_not_expose_a_draft_to_a_user_without_draft_access(): void
    {
        $client = static::createClient();
        self::loginAsUser($client);
        $resources = self::createDraftAndPublishedResources(2);

        self::requestJsonLd($client, 'GET', '/component/dummy_publishable_components?search=is');

        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsPublishedOnlyWithoutDraftResources(self::jsonNode('member'), $resources);
    }
}
