<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\DataProvider\StateProvider;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\State\ProviderInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PublishableReadStateProvider;
use Silverback\ApiComponentsBundle\EventListener\Api\PublishableEventListener;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Symfony\Component\HttpFoundation\Request;

class PublishableReadStateProviderTest extends TestCase
{
    public function test_reading_a_publishable_item_returns_it_with_any_due_draft_merged_and_flushed(): void
    {
        $read = new DummyPublishableComponent();
        $published = new DummyPublishableComponent();
        $request = new Request();
        $listener = $this->createMock(PublishableEventListener::class);
        $listener->expects(self::once())->method('mergeDueDraft')->with($request, $read, true)->willReturn($published);

        self::assertSame($published, $this->provider($read, $listener)->provide(new Get(), [], ['request' => $request]));
    }

    /**
     * @return iterable<string, array{mixed, Operation, bool}>
     */
    public static function unmerged(): iterable
    {
        yield 'a write' => [new DummyPublishableComponent(), new Patch(), true];
        yield 'a collection' => [new DummyPublishableComponent(), new GetCollection(), true];
        yield 'a resource that is not publishable' => [new DummyComponent(), new Get(), true];
        yield 'nothing read' => [null, new Get(), true];
        yield 'an operation that is not HTTP' => [new DummyPublishableComponent(), new Query(), true];
        yield 'no request' => [new DummyPublishableComponent(), new Get(), false];
    }

    #[DataProvider('unmerged')]
    public function test_nothing_is_merged_for(mixed $data, Operation $operation, bool $withRequest): void
    {
        $listener = $this->createMock(PublishableEventListener::class);
        $listener->expects(self::never())->method('mergeDueDraft');

        self::assertSame($data, $this->provider($data, $listener)->provide($operation, [], $withRequest ? ['request' => new Request()] : []));
    }

    public function test_the_inner_provider_receives_the_operation_uri_variables_and_context(): void
    {
        $operation = new Get();
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['id' => 1], ['a' => 'b'])->willReturn(null);

        (new PublishableReadStateProvider($inner, $this->statusChecker(), $this->createStub(PublishableEventListener::class)))->provide($operation, ['id' => 1], ['a' => 'b']);
    }

    private function provider(mixed $data, PublishableEventListener $listener): PublishableReadStateProvider
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return new PublishableReadStateProvider($inner, $this->statusChecker(), $listener);
    }

    private function statusChecker(): PublishableStatusChecker
    {
        $statusChecker = $this->createStub(PublishableStatusChecker::class);
        $statusChecker->method('getAttributeReader')->willReturn(new PublishableAttributeReader($this->createStub(ManagerRegistry::class)));

        return $statusChecker;
    }
}
