<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Concurrency;

use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\ApiRequestTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\AuthenticationTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\Trait\SiteTrait;

final class IfMatchTest extends FunctionalTestCase
{
    use ApiRequestTrait;
    use AuthenticationTrait;
    use ConcurrencyTrait;
    use SiteTrait;

    public function test_a_patch_whose_if_match_is_the_current_version_succeeds(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent('original', 3));

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'edited'], ['If-Match' => '"3"']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('edited', self::bodyOf($response)['reference']);
        self::assertSame(4, self::versionOf($response));
    }

    public function test_a_patch_with_a_stale_if_match_is_412_with_the_current_resource_and_changes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent('theirs', 3);
        $iri = self::iriOf($component);

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'mine'], ['If-Match' => '"2"']);

        self::assertResponseStatusCodeSame(412);
        self::assertStringStartsWith('application/ld+json', $response->getHeaders(false)['content-type'][0] ?? '');
        self::assertSame('"3"', $response->getHeaders(false)['etag'][0] ?? null);
        $body = self::bodyOf($response);
        self::assertSame($iri, $body['@id']);
        self::assertSame('theirs', $body['reference']);
        self::assertSame(3, $body['_metadata']['version'] ?? null);
        self::assertSame(3, self::versionInDatabase($component));
    }

    public function test_a_delete_with_a_stale_if_match_is_412_and_deletes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent('theirs', 3));

        $response = self::requestJsonLd($client, 'DELETE', $iri, null, ['If-Match' => '"2"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(3, self::versionOf($response));
        self::assertResourceExists($iri);
    }

    public function test_a_delete_whose_if_match_is_the_current_version_deletes(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent('theirs', 3));

        self::requestJsonLd($client, 'DELETE', $iri, null, ['If-Match' => '"3"']);

        self::assertResponseStatusCodeSame(204);
        self::assertResourceNotExists($iri);
    }

    public function test_a_write_without_if_match_behaves_as_before(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $iri = self::iriOf(self::createComponent('theirs', 3));

        $response = self::requestJsonLd($client, 'PATCH', $iri, ['reference' => 'mine']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('mine', self::bodyOf($response)['reference']);
    }

    public function test_a_page_data_patch_with_a_stale_if_match_is_412(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $pageData = self::createPageDataWithRoute('/versioned')['pageData'];

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($pageData), ['title' => 'mine'], ['If-Match' => '"2"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(1, self::versionOf($response));
    }

    public function test_a_conflict_at_commit_is_412_with_the_resource_as_committed(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent('theirs');
        self::setVersionBeforeTheNextWrite($component, 2);

        $response = self::requestJsonLd($client, 'PATCH', self::iriOf($component), ['reference' => 'mine'], ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame('theirs', self::bodyOf($response)['reference']);
        self::assertSame(2, self::versionOf($response));
        self::assertSame(2, self::versionInDatabase($component));
        $stored = self::findByIri(self::iriOf($component));
        self::assertInstanceOf(DummyPublishableComponent::class, $stored);
        self::assertSame('theirs', $stored->reference);
    }

    public function test_a_delete_conflicting_at_commit_is_412_and_deletes_nothing(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $component = self::createComponent('theirs');
        $iri = self::iriOf($component);
        self::setVersionBeforeTheNextWrite($component, 2);

        $response = self::requestJsonLd($client, 'DELETE', $iri, null, ['If-Match' => '"1"']);

        self::assertResponseStatusCodeSame(412);
        self::assertSame(2, self::versionOf($response));
        self::assertResourceExists($iri);
    }

    public function test_a_reorder_ignores_if_match(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $created = self::createComponentGroupWithComponents(2);
        $positions = array_map(static fn (ComponentPosition $position): string => self::iriOf($position), $created['positions']);

        self::requestJsonLd($client, 'PATCH', self::iriOf($created['group']) . '/order', ['componentPositions' => [$positions[1], $positions[0]]], ['If-Match' => '"99"']);

        self::assertResponseStatusCodeSame(200);
    }

    public function test_a_move_ignores_if_match_and_raises_the_version_of_the_page_data_it_writes(): void
    {
        $client = static::createClient();
        self::loginAsAdmin($client);
        $site = self::createPageDataWithRoute('/page-data');
        $group = self::createComponentGroupWithComponents(1, 'other_collection')['group'];

        self::requestJsonLd($client, 'POST', self::iriOf($site['component']) . '/move', [
            'from' => ['pageData' => self::iriOf($site['pageData']), 'property' => 'component'],
            'to' => ['componentGroup' => self::iriOf($group)],
        ], ['If-Match' => '"99"']);

        self::assertResponseStatusCodeSame(200);
        $pageData = self::findByIri(self::iriOf($site['pageData']));
        self::assertInstanceOf(PageDataWithComponent::class, $pageData);
        self::assertSame(2, self::versionInDatabase($pageData));
    }
}
