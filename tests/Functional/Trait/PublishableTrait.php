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

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;

trait PublishableTrait
{
    use ResourceTrait;

    /**
     * @template T of object
     *
     * @param T|null $resource
     *
     * @return T|DummyPublishableComponent
     */
    protected static function newPublishable(?\DateTimeInterface $publishedAt = null, ?object $resource = null, bool $forceDraft = false): object
    {
        $resource ??= new DummyPublishableComponent();
        $resource->reference = !$forceDraft && null !== $publishedAt && $publishedAt <= new \DateTime() ? 'is_published' : 'is_draft';
        $resource->setPublishedAt(null === $publishedAt ? null : \DateTime::createFromInterface($publishedAt));

        return $resource;
    }

    /**
     * @template T of object
     *
     * @param T|null $resource
     *
     * @return T|DummyPublishableComponent
     */
    protected static function createPublishable(?\DateTimeInterface $publishedAt = null, ?object $resource = null): object
    {
        $resource = static::newPublishable($publishedAt, $resource);
        static::persistAndFlush($resource);

        return $resource;
    }

    /**
     * @return array{0: DummyPublishableComponent, 1: DummyPublishableComponent}
     */
    protected static function createPublishedWithDraft(?\DateTimeInterface $draftPublishAt = null): array
    {
        $published = static::newPublishable(new \DateTimeImmutable('-10 seconds'));
        $draft = static::newPublishable($draftPublishAt, null, true);
        $draft->setPublishedResource($published);
        static::persistAndFlush($published, $draft);

        return [$published, $draft];
    }

    protected static function createDraftFor(DummyPublishableComponent $published, ?\DateTimeInterface $publishAt = null): DummyPublishableComponent
    {
        $draft = static::newPublishable($publishAt, null, true);
        $draft->setPublishedResource(static::getEntityManager()->find($published::class, $published->getId()));
        static::persistAndFlush($draft);

        return $draft;
    }

    /**
     * @return list<DummyPublishableComponent>
     */
    protected static function createDraftAndPublishedResources(int $count): array
    {
        $resources = [];
        for ($i = 0; $i < $count; ++$i) {
            $publishedNow = static::newPublishable(new \DateTimeImmutable());
            $draftUntilSoon = static::newPublishable(new \DateTimeImmutable('+10 seconds'));
            $draftUntilSoon->setPublishedResource($publishedNow);
            $publishedRecently = static::newPublishable(new \DateTimeImmutable('-10 seconds'));
            $draft = static::newPublishable(null, null, true);
            $draft->setPublishedResource($publishedRecently);
            array_push($resources, $publishedNow, $draftUntilSoon, $publishedRecently, $draft, static::newPublishable(new \DateTimeImmutable('-1 year')), static::newPublishable());
        }
        static::persistAndFlush(...$resources);

        return $resources;
    }

    /**
     * @param list<array<string, mixed>>      $member
     * @param list<DummyPublishableComponent> $resources
     */
    protected static function assertMemberShowsDraftsInsteadOfPublished(array $member, array $resources): void
    {
        $drafts = array_filter($resources, static fn (DummyPublishableComponent $resource): bool => 'is_draft' === $resource->reference);
        $publishedWithoutDrafts = array_map(static::iriOf(...), self::publishedWithoutDrafts($resources));

        self::assertCount(\count($drafts) + \count($publishedWithoutDrafts), $member);
        foreach ($member as $item) {
            self::assertArrayNotHasKey('draftResource', $item, 'A published resource with a draft was returned.');
            if ('is_draft' !== $item['reference']) {
                self::assertContains($item['@id'], $publishedWithoutDrafts, 'An unexpected item was returned: ' . json_encode($item));
            }
        }
    }

    /**
     * @param list<array<string, mixed>>      $member
     * @param list<DummyPublishableComponent> $resources
     */
    protected static function assertMemberShowsPublishedOnly(array $member, array $resources): void
    {
        self::assertCount(\count(array_filter($resources, static fn (DummyPublishableComponent $resource): bool => 'is_published' === $resource->reference)), $member);
        foreach ($member as $item) {
            self::assertSame('is_published', $item['reference'], 'An unexpected item was returned: ' . json_encode($item));
        }
    }

    /**
     * @param list<array<string, mixed>>      $member
     * @param list<DummyPublishableComponent> $resources
     */
    protected static function assertMemberShowsPublishedOnlyWithoutDraftResources(array $member, array $resources): void
    {
        static::assertMemberShowsPublishedOnly($member, $resources);
        foreach ($member as $item) {
            self::assertArrayNotHasKey('draftResource', $item, 'A draft resource was included in the response of a published resource.');
        }
    }

    protected static function placeInComponentPosition(AbstractComponent $component): ComponentPosition
    {
        $group = new ComponentGroup();
        $group->setReference('dummy_collection')->setLocation('nowhere');
        $position = new ComponentPosition();
        $position
            ->setComponentGroup($group)
            ->setComponent(static::getEntityManager()->find($component::class, $component->getId()))
            ->setSortValue(1);
        static::persistAndFlush($group, $position);

        return $position;
    }

    /**
     * @param list<DummyPublishableComponent> $resources
     *
     * @return list<DummyPublishableComponent>
     */
    private static function publishedWithoutDrafts(array $resources): array
    {
        $withDrafts = [];
        foreach ($resources as $resource) {
            if (null !== $published = $resource->getPublishedResource()) {
                $withDrafts[] = $published;
            }
        }

        return array_values(array_filter($resources, static fn (DummyPublishableComponent $resource): bool => 'is_published' === $resource->reference && !\in_array($resource, $withDrafts, true)));
    }
}
