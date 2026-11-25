<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Repository;

use Silverback\ApiComponentsBundle\Entity\Core\Layout;
use Silverback\ApiComponentsBundle\Repository\Core\LayoutRepository;
use Silverback\ApiComponentsBundle\Tests\Helper\OrphanedResource\OrphanedResourceDatabaseTestCase;

final class LayoutRepositoryTest extends OrphanedResourceDatabaseTestCase
{
    public function test_a_layout_is_found_by_its_reference(): void
    {
        $layout = $this->layout();
        $this->entityManager->flush();

        self::assertSame($layout, (new LayoutRepository($this->registry))->findOneByIdOrReference('layout'));
    }

    public function test_a_layout_is_found_by_its_id(): void
    {
        $layout = $this->layout();
        $this->entityManager->flush();

        self::assertSame($layout, (new LayoutRepository($this->registry))->findOneByIdOrReference((string) $layout->getId()));
    }

    public function test_a_reference_wins_over_another_layouts_id(): void
    {
        $byId = $this->layout();
        $byReference = new Layout();
        $byReference->reference = (string) $byId->getId();
        $this->persist($byReference);
        $this->entityManager->flush();

        self::assertSame($byReference, (new LayoutRepository($this->registry))->findOneByIdOrReference((string) $byId->getId()));
    }

    public function test_an_unknown_reference_that_is_not_a_uuid_finds_nothing(): void
    {
        self::assertNull((new LayoutRepository($this->registry))->findOneByIdOrReference('unknown'));
    }
}
