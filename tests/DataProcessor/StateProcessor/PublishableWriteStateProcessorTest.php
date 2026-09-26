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
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Mutation;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\State\ProcessorInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\PublishableWriteStateProcessor;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\HttpFoundation\Request;

class PublishableWriteStateProcessorTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function writes(): iterable
    {
        yield 'POST' => [new Post()];
        yield 'PUT' => [new Put()];
        yield 'PATCH' => [new Patch()];
    }

    #[DataProvider('writes')]
    public function test_writing_a_due_draft_persists_its_published_resource_with_the_draft_merged_and_removal_left_to_the_write(Operation $operation): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $request = new Request();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($published, $operation, ['id' => 1], ['request' => $request])->willReturn('persisted');

        $result = (new PublishableWriteStateProcessor($inner, $this->publishableStatusChecker, $this->publishableDraftMerger))->process($draft, $operation, ['id' => 1], ['request' => $request]);

        self::assertSame('persisted', $result);
        self::assertSame('draft', $published->reference);
        self::assertTrue($this->entityManager->getUnitOfWork()->isScheduledForDelete($draft));
    }

    /**
     * @return iterable<string, array{Operation, bool}>
     */
    public static function unmerged(): iterable
    {
        yield 'a delete' => [new Delete(), true];
        yield 'a read' => [new Get(), true];
        yield 'a collection' => [new GetCollection(), true];
        yield 'an operation that is not HTTP' => [new Mutation(), true];
        yield 'no request' => [new Patch(), false];
    }

    #[DataProvider('unmerged')]
    public function test_a_due_draft_is_persisted_unmerged_for(Operation $operation, bool $withRequest): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($draft)->willReturn('persisted');

        self::assertSame('persisted', (new PublishableWriteStateProcessor($inner, $this->publishableStatusChecker, $this->publishableDraftMerger))->process($draft, $operation, [], $withRequest ? ['request' => new Request()] : []));
        self::assertSame('published', $published->reference);
    }

    public function test_a_resource_that_is_not_publishable_and_no_object_are_persisted_unchanged(): void
    {
        $component = new DummyComponent();
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnArgument(0);
        $processor = new PublishableWriteStateProcessor($inner, $this->publishableStatusChecker, $this->publishableDraftMerger);

        self::assertSame($component, $processor->process($component, new Patch(), [], ['request' => new Request()]));
        self::assertNull($processor->process(null, new Patch(), [], ['request' => new Request()]));
    }
}
