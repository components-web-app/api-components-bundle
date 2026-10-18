<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\EventListener\Doctrine;

use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\DraftComponentReferenceListener;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponentWithRelated;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

#[CoversClass(DraftComponentReferenceListener::class)]
final class DraftComponentReferenceListenerTest extends TestCase
{
    use PublishableDatabaseTrait;

    private ComponentGroup $group;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->entityManager->getEventManager()->addEventListener(Events::onFlush, new DraftComponentReferenceListener(new PublishableAttributeReader($this->registry)));
        $this->group = new ComponentGroup();
        $this->group->reference = 'group';
        $this->group->location = 'group';
        $this->group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($this->group);
        $this->entityManager->flush();
    }

    public function test_a_new_position_given_a_draft_of_a_published_component_holds_the_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);

        $position = $this->newPosition($draft);
        $this->entityManager->flush();

        self::assertSame($published, $position->component);
        self::assertSame((string) $published->getId(), $this->storedComponentId($position));
    }

    public function test_a_new_position_given_a_never_published_draft_holds_the_draft(): void
    {
        $draft = new DummyPublishableComponent();
        $this->entityManager->persist($draft);

        $position = $this->newPosition($draft);
        $this->entityManager->flush();

        self::assertSame($draft, $position->component);
        self::assertSame((string) $draft->getId(), $this->storedComponentId($position));
    }

    public function test_a_component_that_is_not_publishable_is_held_as_it_is(): void
    {
        $component = new DummyComponent();
        $this->entityManager->persist($component);

        $position = $this->newPosition($component);
        $this->entityManager->flush();

        self::assertSame((string) $component->getId(), $this->storedComponentId($position));
    }

    public function test_a_position_re_pointed_to_a_draft_holds_the_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $position = $this->newPosition(new DummyComponent());
        $this->entityManager->flush();

        $position->component = $draft;
        $this->entityManager->flush();

        self::assertSame((string) $published->getId(), $this->storedComponentId($position));
    }

    public function test_a_position_re_pointed_to_a_draft_not_loaded_yet_holds_the_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $position = $this->newPosition(new DummyComponent());
        $this->entityManager->flush();
        $positionId = $position->getId();
        $draftId = $draft->getId();
        $this->entityManager->clear();

        $position = $this->entityManager->find(ComponentPosition::class, $positionId);
        self::assertInstanceOf(ComponentPosition::class, $position);
        $reference = $this->entityManager->getReference(DummyPublishableComponent::class, $draftId);
        self::assertInstanceOf(AbstractComponent::class, $reference);
        $position->component = $reference;
        $this->entityManager->flush();

        self::assertSame((string) $published->getId(), $this->storedComponentId($position));
    }

    public function test_an_update_that_leaves_the_component_alone_does_not_touch_it(): void
    {
        [, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $position = $this->newPosition(new DummyComponent());
        $this->entityManager->flush();
        $this->entityManager->getConnection()->executeStatement('UPDATE _acb_component_position SET component_id = ? WHERE id = ?', [(string) $draft->getId(), (string) $position->getId()]);
        $positionId = $position->getId();
        $this->entityManager->clear();

        $position = $this->entityManager->find(ComponentPosition::class, $positionId);
        self::assertInstanceOf(ComponentPosition::class, $position);
        $position->sortValue = 5;
        $this->entityManager->flush();

        self::assertSame((string) $draft->getId(), $this->storedComponentId($position));
    }

    public function test_a_page_data_component_property_given_a_draft_holds_the_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $component = new DummyComponent();
        $pageData = $this->newPageData();
        $pageData->component = $component;
        $pageData->publishableComponent = $draft;
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        self::assertSame($published, $pageData->publishableComponent);
        self::assertSame($component, $pageData->component);
        self::assertSame(['component' => (string) $component->getId(), 'publishableComponent' => (string) $published->getId()], $this->storedPageDataComponents($pageData));
    }

    public function test_a_page_data_component_property_changed_to_a_draft_holds_the_published_component(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $pageData = $this->newPageData();
        $this->entityManager->flush();

        $pageData->publishableComponent = $draft;
        $this->entityManager->flush();

        self::assertSame((string) $published->getId(), $this->storedPageDataComponents($pageData)['publishableComponent']);
    }

    public function test_a_component_relation_on_another_entity_is_left_alone(): void
    {
        [, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $component = new DummyComponentWithRelated();
        $component->related = $draft;
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        self::assertSame($draft, $component->related);
    }

    private function newPosition(AbstractComponent $component): ComponentPosition
    {
        if (!$this->entityManager->contains($component)) {
            $this->entityManager->persist($component);
        }
        $position = new ComponentPosition();
        $position->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $position->componentGroup = $this->group;
        $position->component = $component;
        $position->sortValue = 0;
        $this->entityManager->persist($position);

        return $position;
    }

    private function newPageData(): PageDataWithComponent
    {
        $page = new Page();
        $page->reference = 'template';
        $page->isTemplate = true;
        $page->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        $pageData->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($page);
        $this->entityManager->persist($pageData);

        return $pageData;
    }

    private function storedComponentId(ComponentPosition $position): ?string
    {
        $value = $this->entityManager->getConnection()->fetchOne('SELECT component_id FROM _acb_component_position WHERE id = ?', [(string) $position->getId()]);

        return false === $value ? null : $value;
    }

    /**
     * @return array{component: ?string, publishableComponent: ?string}
     */
    private function storedPageDataComponents(PageDataWithComponent $pageData): array
    {
        $id = $pageData->getId();
        $this->entityManager->clear();
        $stored = $this->entityManager->find(PageDataWithComponent::class, $id);
        self::assertInstanceOf(PageDataWithComponent::class, $stored);

        return [
            'component' => null === $stored->component ? null : (string) $stored->component->getId(),
            'publishableComponent' => null === $stored->publishableComponent ? null : (string) $stored->publishableComponent->getId(),
        ];
    }
}
