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
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\PublishableWriteStateProcessor;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;

class PublishableWriteStateProcessorTest extends TestCase
{
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
    public function test_the_write_persists_the_resource_with_any_due_draft_merged_without_flushing(Operation $operation): void
    {
        $written = new DummyPublishableComponent();
        $merged = new DummyPublishableComponent();
        $request = new Request();
        $listener = $this->createMock(PublishableEventListener::class);
        $listener->expects(self::once())->method('mergeDueDraft')->with($request, $written)->willReturn($merged);
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($merged, $operation, ['id' => 1], ['request' => $request])->willReturn('persisted');

        self::assertSame('persisted', (new PublishableWriteStateProcessor($inner, $this->statusChecker(), $listener))->process($written, $operation, ['id' => 1], ['request' => $request]));
    }

    /**
     * @return iterable<string, array{mixed, Operation, bool}>
     */
    public static function unmerged(): iterable
    {
        yield 'a delete' => [new DummyPublishableComponent(), new Delete(), true];
        yield 'a read' => [new DummyPublishableComponent(), new Get(), true];
        yield 'a collection' => [new DummyPublishableComponent(), new GetCollection(), true];
        yield 'a resource that is not publishable' => [new DummyComponent(), new Patch(), true];
        yield 'no object' => [null, new Patch(), true];
        yield 'an operation that is not HTTP' => [new DummyPublishableComponent(), new Mutation(), true];
        yield 'no request' => [new DummyPublishableComponent(), new Patch(), false];
    }

    #[DataProvider('unmerged')]
    public function test_the_data_is_persisted_unchanged_for(mixed $data, Operation $operation, bool $withRequest): void
    {
        $listener = $this->createMock(PublishableEventListener::class);
        $listener->expects(self::never())->method('mergeDueDraft');
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with($data)->willReturn('persisted');

        self::assertSame('persisted', (new PublishableWriteStateProcessor($inner, $this->statusChecker(), $listener))->process($data, $operation, [], $withRequest ? ['request' => new Request()] : []));
    }

    private function statusChecker(): PublishableStatusChecker
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));

        return $statusChecker;
    }
}
