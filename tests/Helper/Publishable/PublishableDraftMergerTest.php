<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\Publishable;

use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;

class PublishableDraftMergerTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_reading_a_published_resource_whose_draft_is_due_merges_the_draft_into_it(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $publishedId = $published->getId();
        $draftId = $draft->getId();
        $request = new Request();

        $result = $this->publishableDraftMerger->mergeDueDraft($request, $published, true);

        self::assertSame($published, $result);
        self::assertSame('draft', $published->reference);
        self::assertEquals($publishedId, $published->getId());
        self::assertNull($published->getDraftResource());
        self::assertFalse($request->attributes->has('data'));
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(DummyPublishableComponent::class, $draftId));
        self::assertSame('draft', $this->entityManager->find(DummyPublishableComponent::class, $publishedId)->reference);
    }

    public function test_reading_a_due_draft_merges_it_into_its_published_resource_and_points_the_request_at_it(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $request = new Request();

        $result = $this->publishableDraftMerger->mergeDueDraft($request, $draft, true);

        self::assertSame($published, $result);
        self::assertSame('draft', $published->reference);
        self::assertNull($draft->getPublishedResource());
        self::assertSame($published, $request->attributes->get('data'));
        self::assertEquals($published->getId(), $request->attributes->get('id'));
        $previous = $request->attributes->get('previous_data');
        self::assertInstanceOf(DummyPublishableComponent::class, $previous);
        self::assertNotSame($published, $previous);
    }

    public function test_a_merge_without_a_flush_leaves_the_draft_removal_to_the_write(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));

        $this->publishableDraftMerger->mergeDueDraft(new Request(), $published);

        self::assertTrue($this->entityManager->getUnitOfWork()->isScheduledForDelete($draft));
    }

    public function test_a_published_resource_whose_draft_is_not_due_is_left_alone(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('+1 day'));

        self::assertSame($published, $this->publishableDraftMerger->mergeDueDraft(new Request(), $published, true));
        self::assertSame('published', $published->reference);
        self::assertSame($draft, $published->getDraftResource());
    }

    public function test_a_published_resource_with_an_unpublished_draft_is_left_alone(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), null);

        self::assertSame($published, $this->publishableDraftMerger->mergeDueDraft(new Request(), $published, true));
        self::assertSame($draft, $published->getDraftResource());
    }

    public function test_a_draft_that_is_not_due_is_left_alone(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('+1 day'));
        $request = new Request();

        self::assertSame($draft, $this->publishableDraftMerger->mergeDueDraft($request, $draft, true));
        self::assertSame($published, $draft->getPublishedResource());
        self::assertFalse($request->attributes->has('data'));
    }

    public function test_a_published_resource_with_no_draft_is_left_alone(): void
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 day'));

        self::assertSame($published, $this->publishableDraftMerger->mergeDueDraft(new Request(), $published, true));
    }
}
