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
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\ComponentMoveStateProcessor;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveAccessChecker;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMover;
use Silverback\ApiComponentsBundle\Helper\ComponentMove\ComponentMoveRequestResolver;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentCycleDetector;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionOrderer;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionPlacer;
use Silverback\ApiComponentsBundle\Helper\Timestamped\TimestampedDataPersister;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Metadata\PageDataMetadata;
use Silverback\ApiComponentsBundle\Metadata\PageDataPropertyMetadata;
use Silverback\ApiComponentsBundle\Model\Component\ComponentMove;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageDataWithComponent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ComponentMoveStateProcessorTest extends TestCase
{
    use ComponentPositionDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_the_move_returns_the_iri_of_the_moved_position_with_the_request_echoed(): void
    {
        $this->group('main', ['A' => 0]);
        $this->group('other', []);
        $position = $this->iris['/position/A'];
        \assert($position instanceof ComponentPosition);
        $move = new ComponentMove(['componentPosition' => '/position/A'], ['componentGroup' => '/group/other']);

        $result = $this->processor()->process($move, new Post(), [], ['read_data' => $position->component]);

        self::assertNotSame($move, $result);
        self::assertSame(['componentPosition' => '/position/A'], $result->from);
        self::assertSame(['componentGroup' => '/group/other'], $result->to);
        self::assertSame('/position/A', $result->componentPosition);
        self::assertSame('other', $position->componentGroup?->reference);
    }

    public function test_a_move_into_a_slot_returns_no_position(): void
    {
        $this->group('main', ['A' => 0]);
        $position = $this->iris['/position/A'];
        \assert($position instanceof ComponentPosition);
        $page = new Page();
        $page->isTemplate = true;
        $page->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $pageData = new PageDataWithComponent();
        $pageData->page = $page;
        $pageData->setCreatedAt(new \DateTimeImmutable())->setModifiedAt(new \DateTime());
        $this->entityManager->persist($page);
        $this->entityManager->persist($pageData);
        $this->entityManager->flush();
        $this->iris['/page_data'] = $pageData;

        $result = $this->processor()->process(new ComponentMove(['componentPosition' => '/position/A'], ['pageData' => '/page_data', 'property' => 'component']), new Post(), [], ['read_data' => $position->component]);

        self::assertNull($result->componentPosition);
        self::assertSame(['pageData' => '/page_data', 'property' => 'component'], $result->to);
    }

    public function test_a_body_that_is_not_a_move_is_refused_as_an_empty_move(): void
    {
        $this->group('main', ['A' => 0]);
        $position = $this->iris['/position/A'];
        \assert($position instanceof ComponentPosition);

        $this->expectException(ValidationException::class);

        $this->processor()->process(new \stdClass(), new Post(), [], ['read_data' => $position->component]);
    }

    public function test_a_move_with_no_component_read_is_not_found(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->processor()->process(new ComponentMove(), new Post(), [], ['read_data' => new \stdClass()]);
    }

    private function processor(): ComponentMoveStateProcessor
    {
        $resourceMetadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $resourceMetadataFactory->method('create')->willReturnCallback(static fn (string $class): ResourceMetadataCollection => new ResourceMetadataCollection($class, [
            (new ApiResource(class: $class))->withOperations(new Operations(['patch' => new Patch(uriTemplate: '/items/{id}', uriVariables: ['id']), 'delete' => new Delete(uriTemplate: '/items/{id}', uriVariables: ['id'])])),
        ]));
        $pageDataMetadata = new PageDataMetadata(PageDataWithComponent::class);
        $pageDataMetadata->addProperty(new PageDataPropertyMetadata('component', DummyComponent::class, 'DummyComponent'));
        $pageDataMetadataFactory = $this->createStub(PageDataMetadataFactoryInterface::class);
        $pageDataMetadataFactory->method('create')->willReturn($pageDataMetadata);
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());
        $mover = new ComponentMover(
            $this->registry,
            $pageDataMetadataFactory,
            new ComponentCycleDetector($this->registry, new PublishableAttributeReader($this->registry)),
            new ComponentPositionPlacer(new ComponentPositionOrderer(), $this->resolver()),
            new ComponentMoveAccessChecker($resourceMetadataFactory, $this->createStub(ResourceAccessCheckerInterface::class)),
            $validator,
            new TimestampedDataPersister($this->registry, new TimestampedAttributeReader($this->registry)),
        );

        return new ComponentMoveStateProcessor(new ComponentMoveRequestResolver($this->iriConverter()), $mover, $this->iriConverter());
    }
}
