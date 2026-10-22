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
use Doctrine\ORM\OptimisticLockException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Entity\Utility\VersionedInterface;
use Silverback\ApiComponentsBundle\EventListener\Doctrine\ResourceVersionListener;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;

#[CoversClass(ResourceVersionListener::class)]
final class ResourceVersionListenerTest extends TestCase
{
    use PublishableDatabaseTrait;

    private ResourceVersionListener $listener;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
        $this->listener = new ResourceVersionListener(new PublishableAttributeReader($this->registry));
        $this->entityManager->getEventManager()->addEventListener(Events::onFlush, $this->listener);
    }

    public function test_a_new_resource_is_stored_at_version_one(): void
    {
        $component = $this->persistComponent();

        self::assertSame(1, $component->getResourceVersion());
        self::assertSame(1, $this->storedVersion($component));
    }

    public function test_a_new_resource_cloned_from_another_starts_at_version_one(): void
    {
        $component = new DummyPublishableComponent();
        self::setVersion($component, 6);
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        self::assertSame(1, $this->storedVersion($component));
    }

    public function test_a_new_draft_is_stored_one_above_its_published_resource(): void
    {
        $published = $this->persistComponent();
        $this->setStoredVersion($published, 4);
        $this->entityManager->refresh($published);

        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        self::assertSame(5, $draft->getResourceVersion());
        self::assertSame(5, $this->storedVersion($draft));
        self::assertSame(4, $this->storedVersion($published));
    }

    public function test_a_new_draft_is_stored_one_above_its_published_resource_as_stored_not_as_read(): void
    {
        $published = $this->persistComponent();
        $this->setStoredVersion($published, 4);

        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        self::assertSame(5, $this->storedVersion($draft));
        self::assertSame(4, $this->storedVersion($published));
    }

    public function test_a_new_draft_of_a_published_resource_inserted_in_the_same_flush_is_one_above_it(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($published);
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        self::assertSame(1, $this->storedVersion($published));
        self::assertSame(2, $this->storedVersion($draft));
    }

    public function test_a_new_draft_of_the_expected_published_version_is_stored(): void
    {
        $published = $this->persistComponent();
        $this->listener->expectVersion($published, 1);

        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);
        $this->entityManager->flush();

        self::assertSame(2, $this->storedVersion($draft));
    }

    public function test_a_new_draft_after_the_expected_published_version_was_stored_by_another_write_is_refused(): void
    {
        $published = $this->persistComponent();
        $this->listener->expectVersion($published, 1);
        $this->setStoredVersion($published, 2);

        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $this->entityManager->persist($draft);

        try {
            $this->entityManager->flush();
            self::fail('The draft was not refused.');
        } catch (OptimisticLockException $exception) {
            self::assertSame($published, $exception->getEntity());
        }
        self::assertSame(2, $this->storedVersion($published));
    }

    public function test_an_update_raises_the_stored_version_by_one(): void
    {
        $component = $this->persistComponent();

        $component->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame(2, $component->getResourceVersion());
        self::assertSame(2, $this->storedVersion($component));
    }

    public function test_an_update_raises_the_version_above_one_stored_since_it_was_read(): void
    {
        $component = $this->persistComponent();
        $this->setStoredVersion($component, 5);

        $component->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame(6, $component->getResourceVersion());
        self::assertSame(6, $this->storedVersion($component));
    }

    public function test_an_update_raises_the_version_above_one_set_on_the_resource(): void
    {
        $component = $this->persistComponent();
        self::setVersion($component, 7);

        $component->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame(8, $this->storedVersion($component));
    }

    public function test_a_second_flush_with_nothing_changed_keeps_the_version(): void
    {
        $component = $this->persistComponent();
        $component->reference = 'edited';
        $this->entityManager->flush();

        $this->entityManager->flush();

        self::assertSame(2, $this->storedVersion($component));
    }

    public function test_an_update_of_the_expected_version_stores_the_next_one(): void
    {
        $component = $this->persistComponent();
        $this->listener->expectVersion($component, 1);

        $component->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame(2, $component->getResourceVersion());
        self::assertSame(2, $this->storedVersion($component));
    }

    public function test_an_update_after_the_expected_version_was_stored_by_another_write_is_refused(): void
    {
        $component = $this->persistComponent();
        $this->listener->expectVersion($component, 1);
        $this->setStoredVersion($component, 2);

        $component->reference = 'edited';

        try {
            $this->entityManager->flush();
            self::fail('The update was not refused.');
        } catch (OptimisticLockException $exception) {
            self::assertSame($component, $exception->getEntity());
        }
        self::assertSame(2, $this->storedVersion($component));
        self::assertSame('original', $this->storedReference($component));
    }

    public function test_two_flushes_of_one_expected_resource_both_succeed(): void
    {
        $component = $this->persistComponent();
        $this->listener->expectVersion($component, 1);
        $component->reference = 'first';
        $this->entityManager->flush();

        $component->reference = 'second';
        $this->entityManager->flush();

        self::assertSame(3, $this->storedVersion($component));
    }

    public function test_a_deletion_after_the_expected_version_was_stored_by_another_write_is_refused(): void
    {
        $component = $this->persistComponent();
        $this->listener->expectVersion($component, 1);
        $this->setStoredVersion($component, 2);

        $this->entityManager->remove($component);

        $this->expectException(OptimisticLockException::class);
        $this->entityManager->flush();
    }

    public function test_a_deletion_of_the_expected_version_deletes(): void
    {
        $component = $this->persistComponent();
        $id = $component->getId();
        $this->listener->expectVersion($component, 1);

        $this->entityManager->remove($component);
        $this->entityManager->flush();

        self::assertNull($this->entityManager->find(DummyPublishableComponent::class, $id));
    }

    public function test_a_deletion_with_no_expected_version_deletes_whatever_is_stored(): void
    {
        $component = $this->persistComponent();
        $id = $component->getId();
        $this->setStoredVersion($component, 2);

        $this->entityManager->remove($component);
        $this->entityManager->flush();

        self::assertNull($this->entityManager->find(DummyPublishableComponent::class, $id));
    }

    public function test_reset_forgets_the_expected_versions(): void
    {
        $component = $this->persistComponent();
        $this->listener->expectVersion($component, 1);
        $this->setStoredVersion($component, 2);
        $this->listener->reset();

        $component->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame(3, $this->storedVersion($component));
    }

    public function test_a_resource_that_is_not_versioned_is_left_alone(): void
    {
        $group = new ComponentGroup();
        $group->reference = 'group';
        $group->location = 'group';
        $group->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($group);
        $this->entityManager->flush();

        $group->reference = 'edited';
        $this->entityManager->flush();

        self::assertSame('edited', $this->entityManager->getConnection()->fetchOne('SELECT reference FROM _acb_component_group'));
    }

    public function test_a_versioned_resource_that_is_not_publishable_starts_at_version_one(): void
    {
        $component = new DummyComponent();
        self::setVersion($component, 3);
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        self::assertSame(1, $component->getResourceVersion());
    }

    private function persistComponent(): DummyPublishableComponent
    {
        $component = new DummyPublishableComponent();
        $component->reference = 'original';
        $this->entityManager->persist($component);
        $this->entityManager->flush();

        return $component;
    }

    private static function setVersion(AbstractComponent $component, int $version): void
    {
        (new \ReflectionProperty(AbstractComponent::class, 'resourceVersion'))->setValue($component, $version);
    }

    private function setStoredVersion(AbstractComponent $component, int $version): void
    {
        $this->entityManager->getConnection()->executeStatement(\sprintf('UPDATE _acb_abstract_component SET %s = ? WHERE id = ?', $this->versionColumn()), [$version, (string) $component->getId()]);
    }

    private function storedVersion(AbstractComponent $component): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne(\sprintf('SELECT %s FROM _acb_abstract_component WHERE id = ?', $this->versionColumn()), [(string) $component->getId()]);
    }

    private function versionColumn(): string
    {
        return $this->entityManager->getClassMetadata(AbstractComponent::class)->getColumnName(VersionedInterface::VERSION_FIELD);
    }

    private function storedReference(DummyPublishableComponent $component): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne('SELECT reference FROM _acb_dummy_publishable_component WHERE id = ?', [(string) $component->getId()]);
    }
}
