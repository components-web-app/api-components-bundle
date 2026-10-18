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

use PHPUnit\Framework\AssertionFailedError;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Stub\HubStub;

final class MercureTraitTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use MercureTrait;
    use SiteTrait;

    public function test_an_admin_edit_of_a_routed_page_publishes_an_update_for_it(): void
    {
        $client = static::createClient();
        $pageIri = self::iriOf(self::createRoutedPageWithComponent('/mercure')['page']);
        self::loginAsAdmin($client);
        HubStub::clearPublished();
        self::assertNoMercureUpdatePublished();

        self::requestJsonLd($client, 'PATCH', $pageIri, ['title' => 'Changed']);

        self::assertResponseStatusCodeSame(200);
        self::assertMercureUpdatePublishedFor($pageIri);
        $public = self::mercureUpdateFor($pageIri, false);
        self::assertSame([$pageIri], self::mercureTopicsWithoutOrigin($public));
        self::assertSame($pageIri, self::mercureUpdateData($public)['@id']);
        self::assertEquals(self::mercureUpdates(), self::mercureUpdatesInLastRequest());
        self::assertNotSame([], self::mercureUpdatesFor($pageIri));
        self::assertSame([], self::mercureUpdatesFor('/_/pages/unknown'));
        self::assertNoPublicMercureUpdateContains('a text nobody wrote');
        self::assertStringContainsString($pageIri, self::describeMercureUpdates());
    }

    public function test_set_up_writes_are_recorded_until_cleared(): void
    {
        static::createClient();

        $route = self::createRouteWithPage('/recorded');

        self::assertMercureUpdatePublishedFor(self::iriOf($route));
        HubStub::clearPublished();
        self::assertSame([], self::mercureUpdates());
    }

    public function test_asserting_one_update_fails_when_there_is_none(): void
    {
        static::createClient();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('Expected one private Mercure update for /_/pages/none');

        self::mercureUpdateFor('/_/pages/none', true);
    }

    public function test_a_public_update_containing_the_text_fails_the_assertion(): void
    {
        static::createClient();
        self::createRouteWithPage('/public');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('A public Mercure update contains /public');

        self::assertNoPublicMercureUpdateContains('/public');
    }
}
