<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProcessor\StateProcessor;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentPositionRemovalStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;

class ComponentPositionRemovalStateProcessorTest extends TestCase
{
    public function test_deleting_a_component_removes_its_static_positions_before_the_delete_and_keeps_dynamic_ones(): void
    {
        $component = new DummyComponent();
        $static = (new ComponentPosition())->setComponent($component);
        $dynamic = (new ComponentPosition())->setComponent($component);
        $dynamic->pageDataProperty = 'component';
        $component->addComponentPosition($static)->addComponentPosition($dynamic);
        $calls = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($static)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'remove position';
        });
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'delete';

            return 'deleted';
        });

        $result = $this->processor($inner, $entityManager)->process($component, new Delete(class: DummyComponent::class));

        self::assertSame('deleted', $result);
        self::assertSame(['remove position', 'delete'], $calls);
    }

    public function test_deleting_a_published_component_with_a_draft_hands_its_positions_to_the_draft(): void
    {
        $published = new DummyPublishableComponent();
        $draft = new DummyPublishableComponent();
        $position = (new ComponentPosition())->setComponent($published);
        $published->addComponentPosition($position);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn($draft);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->expects(self::never())->method('remove');

        $this->processor($this->inner(), $entityManager)->process($published, new Delete(class: DummyPublishableComponent::class));

        self::assertSame($draft, $position->component);
    }

    public function test_deleting_a_published_component_without_a_draft_removes_its_positions(): void
    {
        $published = new DummyPublishableComponent();
        $position = (new ComponentPosition())->setComponent($published);
        $published->addComponentPosition($position);
        $classMetadata = $this->createStub(ClassMetadata::class);
        $classMetadata->method('getFieldValue')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($classMetadata);
        $entityManager->expects(self::once())->method('remove')->with($position);

        $this->processor($this->inner(), $entityManager)->process($published, new Delete());
    }

    public function test_nothing_is_removed_without_a_manager_for_positions(): void
    {
        $component = new DummyComponent();
        $component->addComponentPosition((new ComponentPosition())->setComponent($component));
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        self::assertSame('written', (new ComponentPositionRemovalStateProcessor($this->inner(), $registry, $this->statusChecker($registry)))->process($component, new Delete()));
    }

    /**
     * @return iterable<string, array{mixed, Operation}>
     */
    public static function ignored(): iterable
    {
        yield 'an updated component' => [new DummyComponent(), new Patch()];
        yield 'a deleted position' => [new ComponentPosition(), new Delete()];
        yield 'a component through an operation that is not HTTP' => [new DummyComponent(), new Mutation()];
    }

    #[DataProvider('ignored')]
    public function test_no_position_is_touched_for(mixed $data, Operation $operation): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        self::assertSame('written', (new ComponentPositionRemovalStateProcessor($this->inner(), $registry, $this->statusChecker($registry)))->process($data, $operation));
    }

    private function processor(ProcessorInterface $inner, EntityManagerInterface $entityManager): ComponentPositionRemovalStateProcessor
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        return new ComponentPositionRemovalStateProcessor($inner, $registry, $this->statusChecker($registry));
    }

    private function statusChecker(ManagerRegistry $registry): PublishableStatusChecker
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($registry));

        return $statusChecker;
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
