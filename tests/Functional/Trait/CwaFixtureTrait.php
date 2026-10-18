<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\Trait;

use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;

trait CwaFixtureTrait
{
    /**
     * @param \Closure(CwaFixtureBuilder): void $build
     */
    protected static function buildSite(\Closure $build): void
    {
        $manager = static::getEntityManager();
        $cwa = static::getContainer()->get(CwaFixtureBuilder::class)->withManager($manager);
        $build($cwa);
        $cwa->flush();
        static::rebootKernel();
    }
}
