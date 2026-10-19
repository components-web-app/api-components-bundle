<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Doctrine\Extension\ORM;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataCollector\CwaCollectorData;
use Silverback\ApiComponentsBundle\Doctrine\Extension\ORM\PublishableExtension;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class PublishableExtensionTest extends TestCase
{
    use PublishableDatabaseTrait;

    private DummyPublishableComponent $published;

    private DummyPublishableComponent $draft;

    private DummyPublishableComponent $scheduled;

    private DummyPublishableComponent $unpublished;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        [$this->published, $this->draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), null);
        $this->scheduled = $this->persistComponent('scheduled', new \DateTime('+1 day'));
        $this->unpublished = $this->persistComponent('unpublished', null);
        $this->entityManager->flush();
    }

    public function test_a_caller_without_the_publication_permission_lists_only_active_published_components(): void
    {
        self::assertSame(['published'], $this->collectionReferences(false));
    }

    public function test_a_caller_with_the_publication_permission_lists_drafts_in_place_of_their_published_components(): void
    {
        self::assertSame(['draft', 'scheduled', 'unpublished'], $this->collectionReferences(true));
    }

    public function test_a_caller_with_the_publication_permission_asking_for_published_components_lists_only_active_published_components(): void
    {
        self::assertSame(['published'], $this->collectionReferences(true, ['published' => 'true']));
        self::assertSame(['draft', 'scheduled', 'unpublished'], $this->collectionReferences(true, ['published' => 'false']));
    }

    public function test_a_caller_with_the_publication_permission_reads_the_draft_by_its_published_components_id(): void
    {
        self::assertSame(['draft'], $this->itemReferences(true, $this->published));
    }

    public function test_a_caller_with_the_publication_permission_reads_a_component_with_no_draft_by_its_own_id(): void
    {
        self::assertSame(['scheduled'], $this->itemReferences(true, $this->scheduled));
        self::assertSame(['draft'], $this->itemReferences(true, $this->draft));
    }

    public function test_a_caller_without_the_publication_permission_reads_only_an_active_published_component(): void
    {
        self::assertSame(['published'], $this->itemReferences(false, $this->published));
        self::assertSame([], $this->itemReferences(false, $this->scheduled));
        self::assertSame([], $this->itemReferences(false, $this->unpublished));
    }

    public function test_an_item_read_outside_a_request_is_left_alone(): void
    {
        $queryBuilder = $this->itemQueryBuilder($this->scheduled);
        $dql = $queryBuilder->getDQL();

        $this->extension(false, new RequestStack())->applyToItem($queryBuilder, new QueryNameGenerator(), DummyPublishableComponent::class, ['id' => $this->scheduled->getId()]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function test_a_class_which_is_not_publishable_is_left_alone(): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(DummyComponent::class, 'o');
        $dql = $queryBuilder->getDQL();

        $this->extension(false)->applyToCollection($queryBuilder, new QueryNameGenerator(), DummyComponent::class);
        $this->extension(false)->applyToItem($queryBuilder, new QueryNameGenerator(), DummyComponent::class, ['id' => 1]);

        self::assertSame($dql, $queryBuilder->getDQL());
    }

    public function test_each_query_is_recorded_for_the_profiler(): void
    {
        $collectorData = new CwaCollectorData();
        $this->collectionReferences(false, collectorData: $collectorData);
        $this->collectionReferences(true, collectorData: $collectorData);
        $this->itemReferences(false, $this->published, $collectorData);
        $this->itemReferences(true, $this->published, $collectorData);

        self::assertSame([
            [DummyPublishableComponent::class, 'published_only', 'collection'],
            [DummyPublishableComponent::class, 'draft', 'collection'],
            [DummyPublishableComponent::class, 'published_only', 'item'],
            [DummyPublishableComponent::class, 'draft', 'item'],
        ], array_map(static fn (array $query): array => [$query['class'], $query['mode'], $query['queryType']], $collectorData->getPublishableQueries()));
    }

    /**
     * @return list<string>
     */
    private function collectionReferences(bool $granted, array $filters = [], ?CwaCollectorData $collectorData = null): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()->select('o')->from(DummyPublishableComponent::class, 'o');
        $this->extension($granted, collectorData: $collectorData)->applyToCollection($queryBuilder, new QueryNameGenerator(), DummyPublishableComponent::class, null, ['filters' => $filters]);

        return $this->references($queryBuilder);
    }

    /**
     * @return list<string>
     */
    private function itemReferences(bool $granted, DummyPublishableComponent $component, ?CwaCollectorData $collectorData = null): array
    {
        $queryBuilder = $this->itemQueryBuilder($component);
        $this->extension($granted, collectorData: $collectorData)->applyToItem($queryBuilder, new QueryNameGenerator(), DummyPublishableComponent::class, ['id' => $component->getId()]);

        return $this->references($queryBuilder);
    }

    private function itemQueryBuilder(DummyPublishableComponent $component): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select('o')
            ->from(DummyPublishableComponent::class, 'o')
            ->andWhere('o.id = :id_p1')
            ->setParameter('id_p1', $component->getId(), 'uuid');
    }

    /**
     * @return list<string>
     */
    private function references(QueryBuilder $queryBuilder): array
    {
        $this->entityManager->clear();
        $references = array_map(static fn (DummyPublishableComponent $component): string => $component->reference, $queryBuilder->getQuery()->getResult());
        sort($references);

        return $references;
    }

    private function extension(bool $granted, ?RequestStack $requestStack = null, ?CwaCollectorData $collectorData = null): PublishableExtension
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);
        $statusChecker = new PublishableStatusChecker($this->registry, new PublishableAttributeReader($this->registry), $authorizationChecker, 'is_granted("ROLE_ADMIN")');
        if (null === $requestStack) {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());
        }

        return new PublishableExtension($statusChecker, $requestStack, $this->registry, $collectorData);
    }

    private function persistComponent(string $reference, ?\DateTimeInterface $publishedAt): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = $reference;
        $component->setPublishedAt($publishedAt);
        $this->entityManager->persist($component);

        return $component;
    }
}
