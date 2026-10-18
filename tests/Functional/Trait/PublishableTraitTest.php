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

use Silverback\ApiComponentsBundle\Tests\Functional\FunctionalTestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableWithValidation;

final class PublishableTraitTest extends FunctionalTestCase
{
    use AuthenticationTrait;
    use PublishableTrait;

    private const array JSON_LD = ['accept' => 'application/ld+json'];

    public function test_a_resource_published_in_the_past_is_published_and_one_without_a_date_is_a_draft(): void
    {
        static::createClient();

        $published = self::createPublishable(new \DateTimeImmutable('-1 day'));
        $draft = self::createPublishable();
        $scheduled = self::createPublishable(new \DateTimeImmutable('+1 day'), new DummyPublishableWithValidation());

        self::assertSame('is_published', $published->reference);
        self::assertSame('is_draft', $draft->reference);
        self::assertInstanceOf(DummyPublishableWithValidation::class, $scheduled);
        self::assertSame('is_draft', $scheduled->reference);
        self::assertNotNull(self::findByIri(self::iriOf($scheduled)));
    }

    public function test_a_published_resource_with_a_draft_shows_the_draft_to_an_admin(): void
    {
        $client = static::createClient();
        [$published, $draft] = self::createPublishedWithDraft(new \DateTimeImmutable('+1 hour'));
        self::loginAsAdmin($client);

        $response = $client->request('GET', self::iriOf($published), ['headers' => self::JSON_LD]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('is_draft', $response->toArray()['reference']);
        self::assertSame($published, $draft->getPublishedResource());
    }

    public function test_a_draft_is_created_for_an_existing_published_resource(): void
    {
        $client = static::createClient();
        $published = self::createPublishable(new \DateTimeImmutable('-1 day'));

        $draft = self::createDraftFor($published);

        self::assertSame('is_draft', $draft->reference);
        $client->request('GET', self::iriOf($published), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame('is_published', $client->getResponse()?->toArray()['reference']);
    }

    public function test_a_collection_shows_the_drafts_to_an_admin_and_the_published_resources_to_everyone_else(): void
    {
        $client = static::createClient();
        $resources = self::createDraftAndPublishedResources(2);
        self::assertCount(12, $resources);

        $anonymous = $client->request('GET', '/component/dummy_publishable_components', ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsPublishedOnlyWithoutDraftResources($anonymous->toArray()['member'], $resources);

        self::loginAsAdmin($client);
        $admin = $client->request('GET', '/component/dummy_publishable_components', ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertMemberShowsDraftsInsteadOfPublished($admin->toArray()['member'], $resources);
    }

    public function test_a_component_is_placed_in_a_position_of_its_own_group(): void
    {
        $client = static::createClient();
        $component = self::createPublishable(new \DateTimeImmutable('-1 day'));

        $position = self::placeInComponentPosition($component);

        self::assertSame(1, $position->sortValue);
        self::assertSame('dummy_collection', $position->componentGroup?->reference);
        self::loginAsAdmin($client);
        $client->request('GET', self::iriOf($position), ['headers' => self::JSON_LD]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(self::iriOf($component), $client->getResponse()?->toArray()['component']);
        self::assertInstanceOf(DummyPublishableComponent::class, $component);
    }
}
