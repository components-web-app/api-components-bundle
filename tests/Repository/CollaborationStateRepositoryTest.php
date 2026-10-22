<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Repository;

use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\CollaborationState;
use Silverback\ApiComponentsBundle\Repository\Core\CollaborationStateRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

class CollaborationStateRepositoryTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_draft_of_a_published_component_finds_the_published_components_state(): void
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 hour'));
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $state = $this->persistState($published, 'html');
        $this->persistState($published, 'title');

        self::assertSame([$state], $this->repository()->findByPublishedComponent(['component' => $draft, 'field' => 'html']));
    }

    public function test_a_published_component_finds_its_own_state(): void
    {
        $published = new DummyPublishableComponent();
        $published->setPublishedAt(new \DateTime('-1 hour'));
        $state = $this->persistState($published, 'html');

        self::assertSame([$state], $this->repository()->findByPublishedComponent(['component' => $published, 'field' => 'html']));
    }

    public function test_a_never_published_draft_finds_its_own_state(): void
    {
        $draft = new DummyPublishableComponent();
        $state = $this->persistState($draft, 'html');
        $this->persistState(new DummyPublishableComponent(), 'html');

        self::assertSame([$state], $this->repository()->findByPublishedComponent(['component' => $draft, 'field' => 'html']));
    }

    public function test_a_component_that_is_not_publishable_finds_its_own_state(): void
    {
        $component = new DummyComponent();
        $state = $this->persistState($component, 'html');
        $this->persistState(new DummyComponent(), 'html');

        self::assertSame([$state], $this->repository()->findByPublishedComponent(['component' => $component, 'field' => 'html']));
    }

    public function test_criteria_without_a_component_are_used_as_given(): void
    {
        $state = $this->persistState(new DummyComponent(), 'html');
        $this->persistState(new DummyComponent(), 'title');

        self::assertSame([$state], $this->repository()->findByPublishedComponent(['field' => 'html']));
    }

    private function persistState(AbstractComponent $component, string $field): CollaborationState
    {
        $state = (new CollaborationState())->setComponent($component)->setField($field)->setState('AQID')->setHtmlHash('hash');
        $this->entityManager->persist($component);
        $this->entityManager->persist($state);
        $this->entityManager->flush();

        return $state;
    }

    private function repository(): CollaborationStateRepository
    {
        return new CollaborationStateRepository($this->registry, $this->publishableAttributeReader);
    }
}
