<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Validator\Constraints;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Repository\Core\ComponentGroupRepository;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentUiAllowedInGroups;
use Silverback\ApiComponentsBundle\Validator\Constraints\ComponentUiAllowedInGroupsValidator;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<ComponentUiAllowedInGroupsValidator>
 */
final class ComponentUiAllowedInGroupsValidatorTest extends ConstraintValidatorTestCase
{
    private const string DUMMY = '/component/dummy_components';
    private const string PUBLISHABLE = '/component/dummy_publishable_components';

    private ?\WeakMap $originalData = null;

    /** @var list<ComponentGroup> */
    private array $groups = [];

    /** @var list<AbstractComponent> */
    private array $queriedFor = [];

    /** @var list<array{string, int, Operation|null}> */
    private array $iriCalls = [];

    private bool $withEntityManager = true;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->originalData = new \WeakMap();
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturnCallback(fn (object $entity): array => $this->originalData[$entity] ?? []);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturnCallback(static fn (object $entity, string $field): mixed => 'publishedResource' === $field && $entity instanceof DummyPublishableComponent ? $entity->getPublishedResource() : null);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturnCallback(fn (string $class): ObjectManager => $this->withEntityManager ? $entityManager : $this->createStub(ObjectManager::class));

        $repository = $this->createStub(ComponentGroupRepository::class);
        $repository->method('findRestrictingUiOf')->willReturnCallback(function (AbstractComponent $component): array {
            $this->queriedFor[] = $component;

            return $this->groups;
        });

        $iriConverter = $this->createStub(IriConverterInterface::class);
        $iriConverter->method('getIriFromResource')->willReturnCallback(function (object|string $resource, int $referenceType = UrlGeneratorInterface::ABS_PATH, ?Operation $operation = null): string {
            \assert(\is_string($resource));
            $this->iriCalls[] = [$resource, $referenceType, $operation];

            return DummyComponent::class === $resource ? self::DUMMY : self::PUBLISHABLE;
        });

        return new ComponentUiAllowedInGroupsValidator($registry, $repository, new PublishableAttributeReader($this->createStub(ManagerRegistry::class)), $iriConverter);
    }

    public function test_the_constraint_targets_the_class(): void
    {
        self::assertSame(ComponentUiAllowedInGroups::CLASS_CONSTRAINT, (new ComponentUiAllowedInGroups())->getTargets());
    }

    public function test_a_value_that_is_not_a_component_is_ignored(): void
    {
        $this->validator->validate(new \stdClass(), new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
        self::assertSame([], $this->queriedFor);
    }

    public function test_a_component_without_an_entity_manager_is_not_checked(): void
    {
        $this->withEntityManager = false;
        $component = $this->storedComponent('CwaHero', 'CwaBanner');
        $this->groupsAllowing(['ui' => ['CwaHero']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
        self::assertSame([], $this->queriedFor);
    }

    public function test_a_new_component_is_not_checked(): void
    {
        $component = new DummyComponent();
        $component->uiComponent = 'CwaBanner';
        $this->groupsAllowing(['ui' => ['CwaHero']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
        self::assertSame([], $this->queriedFor);
    }

    public function test_a_component_whose_ui_and_styles_are_unchanged_is_not_checked(): void
    {
        $component = $this->storedComponent('CwaBanner', 'CwaBanner', ['style:dark'], ['style:dark']);
        $this->groupsAllowing(['ui' => ['CwaHero'], 'styles' => ['style:large']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
        self::assertSame([], $this->queriedFor);
    }

    public function test_a_changed_ui_its_group_does_not_allow_is_refused_on_ui_component(): void
    {
        $component = $this->storedComponent('CwaHero', 'CwaBanner');
        $this->groupsAllowing(['ui' => ['CwaHero']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->buildViolation('The UI `{{ ui }}` is not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiComponent')
            ->setParameter('{{ ui }}', 'CwaBanner')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_0')
            ->assertRaised();
        self::assertSame([$component], $this->queriedFor);
        self::assertCount(1, $this->iriCalls);
        [$resource, $referenceType, $operation] = $this->iriCalls[0];
        self::assertSame(DummyComponent::class, $resource);
        self::assertSame(UrlGeneratorInterface::ABS_PATH, $referenceType);
        self::assertInstanceOf(GetCollection::class, $operation);
        self::assertSame(DummyComponent::class, $operation->getClass());
    }

    public function test_a_changed_ui_to_null_its_group_does_not_allow_is_refused(): void
    {
        $component = $this->storedComponent('CwaHero', null);
        $this->groupsAllowing(['ui' => ['CwaHero']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->buildViolation('The UI `{{ ui }}` is not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiComponent')
            ->setParameter('{{ ui }}', 'null')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_0')
            ->assertRaised();
    }

    public function test_changed_styles_its_group_does_not_allow_are_refused_on_ui_class_names(): void
    {
        $component = $this->storedComponent('CwaHero', 'CwaHero', null, ['style:large', 'style:dark', 'style:wide']);
        $this->groupsAllowing(['styles' => ['style:large']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->buildViolation('The styles `{{ styles }}` are not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiClassNames')
            ->setParameter('{{ styles }}', 'style:dark, style:wide')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_0')
            ->assertRaised();
    }

    public function test_only_the_changed_ui_is_checked_when_the_styles_already_break_the_rule(): void
    {
        $component = $this->storedComponent('CwaBanner', 'CwaHero', ['style:dark'], ['style:dark']);
        $this->groupsAllowing(['ui' => ['CwaHero'], 'styles' => ['style:large']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
    }

    public function test_only_the_changed_styles_are_checked_when_the_ui_already_breaks_the_rule(): void
    {
        $component = $this->storedComponent('CwaBanner', 'CwaBanner', ['style:dark'], ['style:large']);
        $this->groupsAllowing(['ui' => ['CwaHero'], 'styles' => ['style:large']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
    }

    public function test_every_group_holding_the_component_must_allow_its_ui(): void
    {
        $component = $this->storedComponent(null, 'CwaHero', null, ['style:dark']);
        $this->groupsAllowing(['ui' => ['CwaHero']], ['ui' => ['CwaBanner'], 'styles' => ['style:large']], ['ui' => ['CwaBanner']]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        $this->buildViolation('The UI `{{ ui }}` is not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiComponent')
            ->setParameter('{{ ui }}', 'CwaHero')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_1')
            ->buildNextViolation('The styles `{{ styles }}` are not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiClassNames')
            ->setParameter('{{ styles }}', 'style:dark')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_1')
            ->buildNextViolation('The UI `{{ ui }}` is not allowed for `{{ iri }}` in the component group `{{ reference }}`.')
            ->atPath('property.path.uiComponent')
            ->setParameter('{{ ui }}', 'CwaHero')
            ->setParameter('{{ iri }}', self::DUMMY)
            ->setParameter('{{ reference }}', 'group_2')
            ->assertRaised();
    }

    public function test_a_new_draft_is_compared_with_and_checked_against_its_published_component(): void
    {
        $published = new DummyPublishableComponent();
        $published->uiComponent = 'CwaHero';
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $draft->uiComponent = 'CwaBanner';
        $this->groupsAllowingFor(self::PUBLISHABLE, ['ui' => ['CwaHero']]);

        $this->validator->validate($draft, new ComponentUiAllowedInGroups());

        self::assertSame([$published], $this->queriedFor);
        self::assertCount(1, $this->context->getViolations());
        self::assertSame('property.path.uiComponent', $this->context->getViolations()->get(0)->getPropertyPath());
    }

    public function test_a_new_draft_with_the_ui_and_styles_of_its_published_component_is_not_checked(): void
    {
        $published = new DummyPublishableComponent();
        $published->uiComponent = 'CwaBanner';
        $published->uiClassNames = ['style:dark'];
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $draft->uiComponent = 'CwaBanner';
        $draft->uiClassNames = ['style:dark'];
        $this->groupsAllowingFor(self::PUBLISHABLE, ['ui' => ['CwaHero'], 'styles' => ['style:large']]);

        $this->validator->validate($draft, new ComponentUiAllowedInGroups());

        $this->assertNoViolation();
        self::assertSame([], $this->queriedFor);
    }

    public function test_a_new_draft_whose_styles_differ_from_its_published_component_is_checked(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $draft->uiClassNames = ['style:dark'];
        $this->groupsAllowingFor(self::PUBLISHABLE, ['styles' => ['style:large']]);

        $this->validator->validate($draft, new ComponentUiAllowedInGroups());

        self::assertCount(1, $this->context->getViolations());
        self::assertSame('property.path.uiClassNames', $this->context->getViolations()->get(0)->getPropertyPath());
    }

    public function test_a_stored_draft_is_checked_against_its_published_components_groups(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $draft->setPublishedResource($published);
        $draft->uiComponent = 'CwaBanner';
        $this->originalData[$draft] = ['uiComponent' => null, 'uiClassNames' => null];
        $this->groupsAllowingFor(self::PUBLISHABLE, ['ui' => [null]]);

        $this->validator->validate($draft, new ComponentUiAllowedInGroups());

        self::assertSame([$published], $this->queriedFor);
        self::assertCount(1, $this->context->getViolations());
    }

    public function test_a_stored_publishable_component_with_no_published_version_is_checked_against_its_own_groups(): void
    {
        $component = new DummyPublishableComponent();
        $component->uiComponent = 'CwaBanner';
        $this->originalData[$component] = ['uiComponent' => null, 'uiClassNames' => null];
        $this->groupsAllowingFor(self::PUBLISHABLE, ['ui' => [null]]);

        $this->validator->validate($component, new ComponentUiAllowedInGroups());

        self::assertSame([$component], $this->queriedFor);
        self::assertCount(1, $this->context->getViolations());
    }

    /**
     * @param list<string>|null $originalStyles
     * @param list<string>|null $styles
     */
    private function storedComponent(?string $originalUi, ?string $ui, ?array $originalStyles = null, ?array $styles = null): DummyComponent
    {
        $component = new DummyComponent();
        $component->uiComponent = $ui;
        $component->uiClassNames = $styles;
        $this->originalData[$component] = ['uiComponent' => $originalUi, 'uiClassNames' => $originalStyles];

        return $component;
    }

    /**
     * @param array<mixed> ...$rules
     */
    private function groupsAllowing(array ...$rules): void
    {
        $this->groupsAllowingFor(self::DUMMY, ...$rules);
    }

    /**
     * @param array<mixed> ...$rules
     */
    private function groupsAllowingFor(string $iri, array ...$rules): void
    {
        $this->groups = [];
        foreach ($rules as $index => $rule) {
            $group = new ComponentGroup();
            $group->reference = 'group_' . $index;
            $group->allowedComponentUi = [$iri => $rule];
            $this->groups[] = $group;
        }
    }
}
