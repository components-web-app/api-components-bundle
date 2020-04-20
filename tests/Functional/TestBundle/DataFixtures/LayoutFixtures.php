<?php

/*
 * This file is part of the Silverback API Component Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentBundle\Tests\Functional\TestBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Silverback\ApiComponentBundle\Entity\Core\Layout;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class LayoutFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $layout = new Layout();
        $layout->default = true;
        $manager->persist($layout);
        $manager->flush();
    }
}
