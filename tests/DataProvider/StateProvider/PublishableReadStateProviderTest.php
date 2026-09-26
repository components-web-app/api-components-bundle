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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\PublishableReadStateProvider;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyPublishableComponent;
use Silverback\ApiComponentsBundle\Tests\Helper\Publishable\PublishableDatabaseTrait;
use Symfony\Component\HttpFoundation\Request;

class PublishableReadStateProviderTest extends TestCase
{
    use PublishableDatabaseTrait;

    protected function setUp(): void
    {
        $this->setUpPublishableDatabase();
    }

    public function test_reading_a_due_draft_returns_its_published_resource_with_the_draft_merged_and_removed(): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));
        $draftId = $draft->getId();
        $request = new Request();

        $result = $this->provider($draft)->provide(new Get(), [], ['request' => $request]);

        self::assertSame($published, $result);
        self::assertSame('draft', $published->reference);
        self::assertSame($published, $request->attributes->get('data'));
        $this->entityManager->clear();
        self::assertNull($this->entityManager->find(DummyPublishableComponent::class, $draftId));
    }

    /**
     * @return iterable<string, array{Operation, bool}>
     */
    public static function unmerged(): iterable
    {
        yield 'a write' => [new Patch(), true];
        yield 'a collection' => [new GetCollection(), true];
        yield 'an operation that is not HTTP' => [new Query(), true];
        yield 'no request' => [new Get(), false];
    }

    #[DataProvider('unmerged')]
    public function test_a_due_draft_is_not_merged_for(Operation $operation, bool $withRequest): void
    {
        [$published, $draft] = $this->persistPublishedWithDraft(new \DateTime('-2 days'), new \DateTime('-1 day'));

        self::assertSame($draft, $this->provider($draft)->provide($operation, [], $withRequest ? ['request' => new Request()] : []));
        self::assertSame('published', $published->reference);
        self::assertSame($published, $draft->getPublishedResource());
    }

    public function test_a_resource_that_is_not_publishable_and_nothing_read_are_returned_unchanged(): void
    {
        $component = new DummyComponent();

        self::assertSame($component, $this->provider($component)->provide(new Get(), [], ['request' => new Request()]));
        self::assertNull($this->provider(null)->provide(new Get(), [], ['request' => new Request()]));
    }

    public function test_the_inner_provider_receives_the_operation_uri_variables_and_context(): void
    {
        $operation = new Get();
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['id' => 1], ['a' => 'b'])->willReturn(null);

        (new PublishableReadStateProvider($inner, $this->publishableStatusChecker, $this->publishableDraftMerger))->provide($operation, ['id' => 1], ['a' => 'b']);
    }

    private function provider(?object $data): PublishableReadStateProvider
    {
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($data);

        return new PublishableReadStateProvider($inner, $this->publishableStatusChecker, $this->publishableDraftMerger);
    }
}
