<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Silverback\ApiComponentsBundle\Fixture\AbstractCwaScaffold;
use Silverback\ApiComponentsBundle\Fixture\CwaFixtureBuilder;
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyUploadableAndPublishable;

class HashedAssetScaffold extends AbstractCwaScaffold implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['cwa_hashed_asset'];
    }

    public function build(CwaFixtureBuilder $cwa): void
    {
        $hero = new DummyUploadableAndPublishable();
        $hero->file = $cwa->asset('2beb03392db998dae1af8544147f347c861c506797b8e6a59ceefa3c741252c6');
        $cwa->persist($hero);
    }
}
