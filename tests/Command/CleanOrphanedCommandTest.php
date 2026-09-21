<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Command;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Silverback\ApiComponentsBundle\Command\CleanOrphanedCommand;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentGroup;
use Silverback\ApiComponentsBundle\Helper\OrphanedResourceHelper;
use Silverback\ApiComponentsBundle\Metadata\ComponentUsageMetadata;
use Silverback\ApiComponentsBundle\Metadata\Factory\ComponentUsageMetadataFactory;
use Silverback\ApiComponentsBundle\Metadata\Factory\PageDataMetadataFactoryInterface;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyComponent;
use Symfony\Component\Console\Tester\CommandTester;

class CleanOrphanedCommandTest extends TestCase
{
    public function test_the_component_progress_bar_is_sized_by_the_component_count(): void
    {
        $tester = $this->executeWith(componentCount: 3, usageTotal: 1);

        self::assertStringContainsString('0/3', $tester->getDisplay());
    }

    public function test_orphaned_components_are_removed_and_counted(): void
    {
        $tester = $this->executeWith(componentCount: 2, usageTotal: 0);

        self::assertStringContainsString('Removed 2 orphaned components', $tester->getDisplay());
        self::assertSame(0, $tester->getStatusCode());
    }

    private function executeWith(int $componentCount, int $usageTotal): CommandTester
    {
        $usageMetadataFactory = $this->createStub(ComponentUsageMetadataFactory::class);
        $usageMetadataFactory->method('create')->willReturn(new ComponentUsageMetadata($usageTotal, 0));

        $groupRepository = $this->createStub(ObjectRepository::class);
        $groupRepository->method('findAll')->willReturn([]);

        $componentRepository = $this->createStub(ObjectRepository::class);
        $componentRepository->method('findAll')->willReturn(array_map(static fn () => new DummyComponent(), array_fill(0, $componentCount, null)));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getRepository')->willReturnMap([
            [ComponentGroup::class, null, $groupRepository],
            [AbstractComponent::class, null, $componentRepository],
        ]);

        $helper = new OrphanedResourceHelper($this->createStub(PageDataMetadataFactoryInterface::class), $usageMetadataFactory, $registry);

        $tester = new CommandTester(new CleanOrphanedCommand($helper, $registry));
        $tester->execute([]);

        return $tester;
    }
}
