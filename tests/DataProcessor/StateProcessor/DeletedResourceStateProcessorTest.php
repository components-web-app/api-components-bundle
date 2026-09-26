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
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\AttributeReader\PublishableAttributeReader;
use Silverback\ApiComponentsBundle\DataProcessor\StateProcessor\DeletedResourceStateProcessor;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Page;
use Silverback\ApiComponentsBundle\Entity\Core\Route;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;

class DeletedResourceStateProcessorTest extends TestCase
{
    public function test_deleting_a_routed_page_removes_its_route_before_the_delete(): void
    {
        $page = new Page();
        $route = (new Route())->setPage($page);
        $page->setRoute($route);
        $calls = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($route)->willReturnCallback(static function () use (&$calls): void {
            $calls[] = 'remove route';
        });
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'delete';

            return 'deleted';
        });
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($entityManager);

        $result = (new DeletedResourceStateProcessor($inner, $this->helper($registry)))->process($page, new Delete(class: Page::class));

        self::assertSame('deleted', $result);
        self::assertSame(['remove route', 'delete'], $calls);
    }

    /**
     * @return iterable<string, array{Operation}>
     */
    public static function notDeletes(): iterable
    {
        yield 'an update' => [new Patch()];
        yield 'an operation that is not HTTP' => [new Mutation()];
    }

    #[DataProvider('notDeletes')]
    public function test_nothing_cascades_for(Operation $operation): void
    {
        $page = new Page();
        $page->setRoute((new Route())->setPage($page));
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::never())->method('getManagerForClass');

        self::assertSame('written', (new DeletedResourceStateProcessor($this->inner(), $this->helper($registry)))->process($page, $operation));
    }

    public function test_the_inner_processor_receives_the_data_operation_uri_variables_and_context(): void
    {
        $operation = new Patch();
        $inner = $this->createMock(ProcessorInterface::class);
        $inner->expects(self::once())->method('process')->with('data', $operation, ['id' => 1], ['a' => 'b']);

        (new DeletedResourceStateProcessor($inner, $this->helper($this->createStub(ManagerRegistry::class))))->process('data', $operation, ['id' => 1], ['a' => 'b']);
    }

    private function helper(ManagerRegistry $registry): OrphanedResourceHelper
    {
        return new OrphanedResourceHelper(
            $this->createStub(PageDataMetadataFactoryInterface::class),
            $this->createStub(ComponentUsageMetadataFactory::class),
            $registry,
            new PublishableAttributeReader($registry),
            $this->createStub(PageDataProvider::class),
        );
    }

    private function inner(): ProcessorInterface
    {
        $inner = $this->createStub(ProcessorInterface::class);
        $inner->method('process')->willReturn('written');

        return $inner;
    }
}
