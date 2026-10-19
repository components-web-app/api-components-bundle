<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Helper\ComponentMove;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\AttributeReader\TimestampedAttributeReader;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveAccessChecker;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveEndpoint;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMover;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionPlacer;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor\ComponentPositionDatabaseTrait;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ComponentMoverTest extends TestCase
{
    use ComponentPositionDatabaseTrait;

    /** @var list<array{string, string, ?object, ?object}> */
    private array $checked = [];

    /** @var list<string> */
    private array $denied = [];

    /** @var array<class-string, list<ConstraintViolation>> */
    private array $violations = [];

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_a_position_moved_to_another_group_goes_at_its_end_and_is_the_same_position(): void
    {
        $main = $this->group('main', ['A' => 0, 'B' => 1]);
        $other = $this->group('other', ['X' => 0, 'Y' => 5]);
        $position = $this->named('A');

        $result = $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::group('to', $other, null, null));

        self::assertSame($position, $result);
        self::assertSame('X0 Y5 A6', $this->stored($other));
        self::assertSame('B1', $this->stored($main));
    }

    public function test_a_position_moved_to_another_group_after_an_anchor_is_placed_after_it(): void
    {
        $this->group('main', ['A' => 0]);
        $other = $this->group('other', ['X' => 0, 'Y' => 5]);

        $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::group('to', $other, '/position/X', null));

        self::assertSame('X0 A1 Y5', $this->stored($other));
    }

    public function test_a_position_moved_within_its_group_before_an_anchor_is_reordered(): void
    {
        $main = $this->group('main', ['A' => 0, 'B' => 1, 'C' => 2]);

        $this->mover()->move($this->componentOf('C'), ComponentMoveEndpoint::position('from', $this->named('C')), ComponentMoveEndpoint::group('to', $main, null, '/position/A'));

        self::assertSame('C2 A3 B4', $this->stored($main));
    }

    public function test_a_position_moved_to_another_group_is_checked_as_a_patch_of_the_position(): void
    {
        $this->group('main', ['A' => 0]);
        $other = $this->group('other', []);
        $position = $this->named('A');

        $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::group('to', $other, null, null));

        self::assertCount(2, $this->checked);
        self::assertSame([ComponentPosition::class, 'patch', $position, $position], $this->checked[0]);
        self::assertSame([ComponentPosition::class, 'patch_post_denormalize', $position], \array_slice($this->checked[1], 0, 3));
        self::assertInstanceOf(ComponentPosition::class, $this->checked[1][3]);
        self::assertNotSame($position, $this->checked[1][3]);
    }

    public function test_a_moved_position_keeps_its_creation_date_and_gets_a_new_modification_date(): void
    {
        $this->group('main', ['A' => 0]);
        $other = $this->group('other', []);
        $position = $this->named('A');
        $createdAt = $position->getCreatedAt();
        $modifiedAt = new \DateTime('-2 days');
        $position->setModifiedAt($modifiedAt);

        $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::group('to', $other, null, null));

        self::assertSame($createdAt, $position->getCreatedAt());
        self::assertGreaterThan($modifiedAt, $position->getModifiedAt());
    }

    public function test_a_component_moved_from_a_slot_to_a_group_gets_a_new_timestamped_position_and_the_slot_is_emptied(): void
    {
        $main = $this->group('main', ['A' => 0, 'B' => 1]);
        $component = new DummyComponent();
        $pageData = $this->pageData($component);

        $result = $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::group('to', $main, null, null));

        self::assertInstanceOf(ComponentPosition::class, $result);
        self::assertSame($component, $result->component);
        self::assertNotNull($result->getId());
        self::assertInstanceOf(\DateTimeImmutable::class, $result->getCreatedAt());
        self::assertSame('A0 B1 new2', $this->stored($main));
        self::assertNull($this->reloaded($pageData)->component);
    }

    public function test_a_component_moved_from_a_slot_to_a_group_is_checked_as_a_create_and_a_page_data_patch(): void
    {
        $main = $this->group('main', []);
        $component = new DummyComponent();
        $pageData = $this->pageData($component);

        $result = $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::group('to', $main, null, null));

        self::assertSame([
            [ComponentPosition::class, 'post', null, null],
            [PageDataWithComponent::class, 'patch', $pageData, $pageData],
            [ComponentPosition::class, 'post_post_denormalize', $result, null],
        ], \array_slice($this->checked, 0, 3));
        self::assertSame([PageDataWithComponent::class, 'patch_post_denormalize', $pageData], \array_slice($this->checked[3], 0, 3));
        self::assertNotSame($pageData, $this->checked[3][3]);
        self::assertCount(4, $this->checked);
    }

    public function test_a_position_moved_into_a_slot_sets_the_slot_and_removes_only_the_position(): void
    {
        $main = $this->group('main', ['A' => 0, 'B' => 1]);
        $component = $this->componentOf('A');
        $componentId = $component->getId();
        $pageData = $this->pageData(null);
        $position = $this->named('A');

        $result = $this->mover()->move($component, ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::slot('to', $pageData, 'component'));

        self::assertNull($result);
        self::assertSame('B1', $this->stored($main));
        self::assertSame((string) $componentId, (string) $this->reloaded($pageData)->component?->getId());
        self::assertNotNull($this->entityManager->find(DummyComponent::class, $componentId));
    }

    public function test_a_position_moved_into_a_slot_is_checked_as_a_delete_and_a_page_data_patch(): void
    {
        $this->group('main', ['A' => 0]);
        $pageData = $this->pageData(null);
        $position = $this->named('A');

        $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::slot('to', $pageData, 'component'));

        self::assertSame([ComponentPosition::class, 'delete', $position, $position], $this->checked[0]);
        self::assertSame([PageDataWithComponent::class, 'patch', $pageData, $pageData], $this->checked[1]);
        self::assertSame([PageDataWithComponent::class, 'patch_post_denormalize', $pageData], \array_slice($this->checked[2], 0, 3));
        self::assertCount(3, $this->checked);
    }

    public function test_a_component_moved_between_two_page_data_slots_sets_one_and_empties_the_other(): void
    {
        $component = new DummyComponent();
        $source = $this->pageData($component);
        $target = $this->pageData(null);
        $modifiedAt = new \DateTime('-2 days');
        $target->setModifiedAt($modifiedAt);

        $result = $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $source, 'component'), ComponentMoveEndpoint::slot('to', $target, 'component'));

        self::assertNull($result);
        self::assertGreaterThan($modifiedAt, $target->getModifiedAt());
        self::assertNull($this->reloaded($source)->component);
        self::assertSame((string) $component->getId(), (string) $this->reloaded($target)->component?->getId());
    }

    public function test_a_published_component_moved_by_its_drafts_iri_is_moved(): void
    {
        $main = $this->group('main', []);
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-1 day'), null);
        $position = $this->position($main, 0);
        $position->component = $published;
        $this->entityManager->flush();
        $pageData = $this->pageData(null);

        $this->mover()->move($draft, ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::slot('to', $pageData, 'publishableComponent'));

        self::assertSame((string) $published->getId(), (string) $this->reloaded($pageData)->publishableComponent?->getId());
    }

    public function test_a_dynamic_position_cannot_be_moved(): void
    {
        $main = $this->group('main', []);
        $position = $this->position($main, 0);
        $position->component = null;
        $position->pageDataProperty = 'component';
        $position->pageDataClass = PageDataWithComponent::class;
        $this->entityManager->flush();

        $this->assertRefused('from.componentPosition', ComponentMover::DYNAMIC_POSITION_MESSAGE, fn () => $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::group('to', $main, null, null)));
    }

    public function test_a_position_with_no_component_cannot_be_moved(): void
    {
        $main = $this->group('main', []);
        $position = $this->position($main, 0);
        $position->component = null;

        $this->assertRefused('from.componentPosition', ComponentMover::EMPTY_POSITION_MESSAGE, fn () => $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::position('from', $position), ComponentMoveEndpoint::group('to', $main, null, null)));
    }

    public function test_a_position_holding_another_component_cannot_be_moved(): void
    {
        $main = $this->group('main', ['A' => 0]);

        $this->assertRefused('from.componentPosition', ComponentMover::OTHER_POSITION_COMPONENT_MESSAGE, fn () => $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::group('to', $main, null, null)));
        self::assertSame('A0', $this->stored($main));
    }

    public function test_a_property_that_cannot_hold_the_component_cannot_be_moved_from(): void
    {
        $main = $this->group('main', []);
        $component = new DummyComponent();
        $pageData = $this->pageData($component);

        try {
            $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $pageData, 'publishableComponent'), ComponentMoveEndpoint::group('to', $main, null, null));
            self::fail('The move was not refused.');
        } catch (ValidationException $exception) {
            $violation = $exception->getConstraintViolationList()->get(0);
            self::assertSame('from.property', $violation->getPropertyPath());
            self::assertSame('`publishableComponent` is not a component-typed property on `' . PageDataWithComponent::class . '`', $violation->getMessage());
            self::assertSame(['{{ property }}' => 'publishableComponent', '{{ class }}' => PageDataWithComponent::class], $violation->getParameters());
        }
    }

    public function test_an_empty_property_cannot_be_moved_from(): void
    {
        $main = $this->group('main', []);
        $pageData = $this->pageData(null);

        $this->assertRefused('from.property', ComponentMover::EMPTY_PROPERTY_MESSAGE, fn () => $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::group('to', $main, null, null)));
    }

    public function test_a_property_holding_another_component_cannot_be_moved_from(): void
    {
        $main = $this->group('main', []);
        $pageData = $this->pageData(new DummyComponent());

        $this->assertRefused('from.property', ComponentMover::OTHER_PROPERTY_COMPONENT_MESSAGE, fn () => $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::group('to', $main, null, null)));
        self::assertSame('', $this->stored($main));
    }

    public function test_a_component_cannot_be_moved_to_the_property_it_comes_from(): void
    {
        $component = new DummyComponent();
        $pageData = $this->pageData($component);

        $this->assertRefused('to', ComponentMover::SAME_PROPERTY_MESSAGE, fn () => $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::slot('to', $pageData, 'component')));
    }

    public function test_a_component_can_be_moved_to_the_same_property_of_another_page_data_resource(): void
    {
        $component = new DummyComponent();
        $source = $this->pageData($component);
        $target = $this->pageData(null);

        $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $source, 'component'), ComponentMoveEndpoint::slot('to', $target, 'component'));

        self::assertSame((string) $component->getId(), (string) $this->reloaded($target)->component?->getId());
    }

    public function test_a_component_cannot_be_moved_into_a_property_that_cannot_hold_it(): void
    {
        $this->group('main', ['A' => 0]);
        $pageData = $this->pageData(null);
        $pageData->publishableComponent = null;

        $this->assertRefused('to.property', '`publishableComponent` is not a component-typed property on `' . PageDataWithComponent::class . '`', fn () => $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::slot('to', $pageData, 'publishableComponent')));
    }

    public function test_a_component_cannot_be_moved_into_a_property_that_already_holds_one(): void
    {
        $main = $this->group('main', ['A' => 0]);
        $pageData = $this->pageData(new DummyComponent());

        $this->assertRefused('to.property', ComponentMover::OCCUPIED_PROPERTY_MESSAGE, fn () => $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::slot('to', $pageData, 'component')));
        self::assertSame('A0', $this->stored($main));
    }

    public function test_a_violation_of_the_position_or_the_page_data_is_reported_at_the_part_of_the_move_it_concerns(): void
    {
        $main = $this->group('main', []);
        $component = new DummyComponent();
        $source = $this->pageData($component);
        $this->violations[ComponentPosition::class] = [
            $this->violation('after'),
            $this->violation('before'),
            $this->violation('component'),
            $this->violation(''),
        ];
        $this->violations[PageDataWithComponent::class] = [
            $this->violation('component'),
            $this->violation('title'),
        ];

        try {
            $this->mover()->move($component, ComponentMoveEndpoint::slot('from', $source, 'component'), ComponentMoveEndpoint::group('to', $main, '/position/X', null));
            self::fail('The move was not refused.');
        } catch (ValidationException $exception) {
            $paths = [];
            foreach ($exception->getConstraintViolationList() as $violation) {
                $paths[] = $violation->getPropertyPath();
            }
            self::assertSame(['to.after', 'to.before', 'to.componentGroup', 'to.componentGroup', 'from.property', 'from.pageData'], $paths);
            $first = $exception->getConstraintViolationList()->get(0);
            self::assertSame('Message at after', $first->getMessage());
            self::assertSame('Template at {{ path }}', $first->getMessageTemplate());
            self::assertSame(['{{ path }}' => 'after'], $first->getParameters());
            self::assertSame('invalid after', $first->getInvalidValue());
            self::assertSame(2, $first->getPlural());
            self::assertSame('code-after', $first->getCode());
            self::assertSame('root', $first->getRoot());
            self::assertInstanceOf(NotBlank::class, $first instanceof ConstraintViolation ? $first->getConstraint() : null);
        }

        self::assertSame((string) $component->getId(), (string) $this->reloaded($source)->component?->getId());
        self::assertSame('', $this->stored($main));
    }

    public function test_a_violation_of_the_target_page_data_is_reported_at_the_target(): void
    {
        $this->group('main', ['A' => 0]);
        $pageData = $this->pageData(null);
        $this->violations[PageDataWithComponent::class] = [$this->violation('component'), $this->violation('title')];

        try {
            $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::slot('to', $pageData, 'component'));
            self::fail('The move was not refused.');
        } catch (ValidationException $exception) {
            $paths = [];
            foreach ($exception->getConstraintViolationList() as $violation) {
                $paths[] = $violation->getPropertyPath();
            }
            self::assertSame(['to.property', 'to.pageData'], $paths);
        }
    }

    public function test_a_refused_write_moves_nothing(): void
    {
        $main = $this->group('main', ['A' => 0]);
        $other = $this->group('other', []);
        $this->denied = ['patch_post_denormalize'];

        try {
            $this->mover()->move($this->componentOf('A'), ComponentMoveEndpoint::position('from', $this->named('A')), ComponentMoveEndpoint::group('to', $other, null, null));
            self::fail('The move was not refused.');
        } catch (AccessDeniedException) {
        }

        self::assertSame('A0', $this->stored($main));
        self::assertSame('', $this->stored($other));
    }

    public function test_a_refused_write_is_refused_before_the_move_is_validated(): void
    {
        $main = $this->group('main', []);
        $pageData = $this->pageData(null);
        $this->denied = ['patch'];

        $this->expectException(AccessDeniedException::class);

        $this->mover()->move(new DummyComponent(), ComponentMoveEndpoint::slot('from', $pageData, 'component'), ComponentMoveEndpoint::group('to', $main, null, null));
    }

    private function mover(): ComponentMover
    {
        $pageDataMetadata = new PageDataMetadata(PageDataWithComponent::class);
        $pageDataMetadata->addProperty(new PageDataPropertyMetadata('component', DummyComponent::class, 'DummyComponent'));
        $pageDataMetadata->addProperty(new PageDataPropertyMetadata('publishableComponent', DummyPublishableComponent::class, 'DummyPublishableComponent'));
        $pageDataMetadataFactory = $this->createStub(PageDataMetadataFactoryInterface::class);
        $pageDataMetadataFactory->method('create')->willReturn($pageDataMetadata);

        $resourceMetadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')->willReturnCallback(static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, [
            (new ApiResource(class: $class))->withOperations(new Operations([
                'post' => new Post(uriTemplate: '/items', security: 'post', securityPostDenormalize: 'post_post_denormalize'),
                'patch' => new Patch(uriTemplate: '/items/{id}', uriVariables: ['id'], security: 'patch', securityPostDenormalize: 'patch_post_denormalize'),
                'delete' => new Delete(uriTemplate: '/items/{id}', uriVariables: ['id'], security: 'delete'),
            ])),
        ]));
        $resourceAccessChecker = $this->createStub(ResourceAccessCheckerInterface::class);
        $resourceAccessChecker->method('isGranted')->willReturnCallback(function (string $class, string $expression, array $variables): bool {
            $this->checked[] = [$class, $expression, $variables['object'], $variables['previous_object']];

            return !\in_array($expression, $this->denied, true);
        });

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturnCallback(fn (object $value): ConstraintViolationList => new ConstraintViolationList($this->violations[$value::class] ?? []));

        return new ComponentMover(
            $this->registry,
            $pageDataMetadataFactory,
            new ComponentCycleDetector($this->registry, new PublishableAttributeReader($this->registry)),
            new ComponentPositionPlacer(new ComponentPositionOrderer(), $this->resolver()),
            new ComponentMoveAccessChecker($resourceMetadataFactory, $resourceAccessChecker),
            $validator,
            new TimestampedDataPersister($this->registry, new TimestampedAttributeReader($this->registry)),
        );
    }

    private function named(string $name): ComponentPosition
    {
        $position = $this->iris['/position/' . $name];
        \assert($position instanceof ComponentPosition);

        return $position;
    }

    private function componentOf(string $name): AbstractComponent
    {
        $component = $this->named($name)->component;
        \assert($component instanceof AbstractComponent);

        return $component;
    }

    private function pageData(?AbstractComponent $component): PageDataWithComponent
    {
        $page = new Page();
        $page->isTemplate = true;
        $page->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        $pageData->component = $component;
        $pageData->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($page);
        if ($component) {
            $this->entityManager->persist($component);
        }
        $this->entityManager->persist($pageData);
        $this->entityManager->flush();

        return $pageData;
    }

    private function reloaded(PageDataWithComponent $pageData): PageDataWithComponent
    {
        $id = $pageData->getId();
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(PageDataWithComponent::class, $id);
        \assert($reloaded instanceof PageDataWithComponent);

        return $reloaded;
    }

    private function violation(string $path): ConstraintViolation
    {
        return new ConstraintViolation('Message at ' . $path, 'Template at {{ path }}', ['{{ path }}' => $path], 'root', $path, 'invalid ' . $path, 2, 'code-' . $path, new NotBlank());
    }

    private function assertRefused(string $propertyPath, string $message, \Closure $move): void
    {
        try {
            $move();
            self::fail('The move was not refused.');
        } catch (ValidationException $exception) {
            $violations = $exception->getConstraintViolationList();
            self::assertCount(1, $violations);
            self::assertSame($propertyPath, $violations->get(0)->getPropertyPath());
            self::assertSame($message, $violations->get(0)->getMessage());
        }
    }
}
