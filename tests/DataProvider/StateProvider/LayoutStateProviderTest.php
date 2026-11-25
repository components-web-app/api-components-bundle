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

use ApiPlatform\Doctrine\Orm\State\CollectionProvider;
use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Silverback\ApiComponentsBundle\DataProvider\StateProvider\LayoutStateProvider;
use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;

final class LayoutStateProviderTest extends TestCase
{
    public function test_a_collection_is_provided_by_the_doctrine_collection_provider(): void
    {
        $default = $this->createMock(ProviderInterface::class);
        $default->expects(self::once())->method('provide')
            ->with(self::callback(static fn (Operation $operation) => CollectionProvider::class === $operation->getProvider()), ['a' => 1], ['b' => 2])
            ->willReturn(['collection']);

        self::assertSame(['collection'], (new LayoutStateProvider($this->createStub(LayoutRepository::class), $default))->provide(new GetCollection(), ['a' => 1], ['b' => 2]));
    }

    public function test_a_string_identifier_is_looked_up_by_id_or_reference(): void
    {
        $layout = new Layout();
        $repository = $this->createMock(LayoutRepository::class);
        $repository->expects(self::once())->method('findOneByIdOrReference')->with('primary')->willReturn($layout);
        $default = $this->createMock(ProviderInterface::class);
        $default->expects(self::never())->method('provide');

        self::assertSame($layout, (new LayoutStateProvider($repository, $default))->provide(new Get(), ['id' => 'primary']));
    }

    public function test_a_uuid_identifier_is_looked_up_by_its_string_so_a_reference_can_win(): void
    {
        $uuid = Uuid::uuid4();
        $layout = new Layout();
        $repository = $this->createMock(LayoutRepository::class);
        $repository->expects(self::once())->method('findOneByIdOrReference')->with($uuid->toString())->willReturn($layout);

        self::assertSame($layout, (new LayoutStateProvider($repository, $this->createStub(ProviderInterface::class)))->provide(new Get(), ['id' => $uuid]));
    }

    public function test_any_other_identifier_is_provided_by_the_doctrine_item_provider(): void
    {
        $layout = new Layout();
        $default = $this->createMock(ProviderInterface::class);
        $default->expects(self::once())->method('provide')
            ->with(self::callback(static fn (Operation $operation) => ItemProvider::class === $operation->getProvider()), ['id' => 1], ['b' => 2])
            ->willReturn($layout);

        self::assertSame($layout, (new LayoutStateProvider($this->createStub(LayoutRepository::class), $default))->provide(new Get(), ['id' => 1], ['b' => 2]));
    }
}
