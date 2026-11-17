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
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\TemplatePageDataCountStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Helper\Page\TemplatePageDataCounter;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\PageData;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class TemplatePageDataCountStateProviderTest extends OrphanedResourceDatabaseTestCase
{
    private TemplatePageDataCounter $counter;

    public function test_an_admins_page_collection_counts_every_template_page_in_it_before_it_is_normalized(): void
    {
        $pages = $this->templates();
        $operation = new GetCollection(class: Page::class);
        $inner = $this->createMock(ProviderInterface::class);
        $inner->expects(self::once())->method('provide')->with($operation, ['id' => 'x'], ['c' => 1])->willReturn($pages);

        $result = $this->provider($inner, true)->provide($operation, ['id' => 'x'], ['c' => 1]);

        self::assertSame($pages, $result);
        self::assertCount(1, $this->queryLogger->queries);
        $this->queryLogger->queries = [];
        self::assertSame([1, 2], [$this->counter->count($pages[0]), $this->counter->count($pages[1])]);
        self::assertSame([], $this->queryLogger->queries);
    }

    /**
     * @return iterable<string, array{Operation, bool}>
     */
    public static function nothingCounted(): iterable
    {
        yield 'a caller without the publishable permission' => [new GetCollection(class: Page::class), false];
        yield 'a single page' => [new Get(class: Page::class), true];
        yield 'a collection of another resource' => [new GetCollection(class: Layout::class), true];
    }

    #[DataProvider('nothingCounted')]
    public function test_nothing_is_counted_for(Operation $operation, bool $granted): void
    {
        $pages = $this->templates();
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($pages);

        self::assertSame($pages, $this->provider($inner, $granted)->provide($operation));
        self::assertSame([], $this->queryLogger->queries);
    }

    public function test_a_page_collection_operation_providing_no_collection_is_returned_as_it_is(): void
    {
        $page = $this->templates()[0];
        $inner = $this->createStub(ProviderInterface::class);
        $inner->method('provide')->willReturn($page);

        self::assertSame($page, $this->provider($inner, true)->provide(new GetCollection(class: Page::class)));
        self::assertSame([], $this->queryLogger->queries);
    }

    /**
     * @param ProviderInterface<object> $inner
     */
    private function provider(ProviderInterface $inner, bool $granted): TemplatePageDataCountStateProvider
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);
        $this->counter = new TemplatePageDataCounter($this->entityManager);

        return new TemplatePageDataCountStateProvider(
            $inner,
            $this->counter,
            new PublishableStatusChecker($this->registry, new PublishableAttributeReader($this->registry), $authorizationChecker, 'is_granted("ROLE_ADMIN")'),
        );
    }

    /**
     * @return list<Page>
     */
    private function templates(): array
    {
        $pages = [];
        foreach ([1, 2] as $count) {
            $page = $this->page();
            for ($i = 0; $i < $count; ++$i) {
                $item = new PageData();
                $item->page = $page;
                $this->persist($item);
            }
            $pages[] = $page;
        }
        $this->entityManager->flush();
        $this->queryLogger->queries = [];

        return $pages;
    }
}
