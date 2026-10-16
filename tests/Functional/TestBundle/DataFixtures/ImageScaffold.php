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
use Silverback\ApiComponentsBundle\Tests\Functional\TestBundle\Entity\DummyMercureUploadableWithImagineFilters;
use Symfony\Component\HttpFoundation\File\File;

class ImageScaffold extends AbstractCwaScaffold implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['cwa_image'];
    }

    public function build(CwaFixtureBuilder $cwa): void
    {
        $image = new DummyMercureUploadableWithImagineFilters();
        $image->file = new File(__DIR__ . '/../../../../features/assets/files/image.png');
        $cwa->persist($image);
    }
}
